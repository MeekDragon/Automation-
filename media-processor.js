import sharp from 'sharp';
import ffmpeg from 'fluent-ffmpeg';
import { exec } from 'child_process';
import path from 'path';
import fs from 'fs';

// Check if FFmpeg is installed on the system path
let isFfmpegAvailable = false;

export function checkFfmpeg() {
  return new Promise((resolve) => {
    exec('ffmpeg -version', (error) => {
      if (error) {
        console.warn('FFmpeg is not installed or not in PATH. Video scaling/transcoding will be skipped; raw videos will be uploaded directly.');
        isFfmpegAvailable = false;
      } else {
        console.log('FFmpeg detected successfully.');
        isFfmpegAvailable = true;
      }
      resolve(isFfmpegAvailable);
    });
  });
}

// Perform initial check
checkFfmpeg();

export async function processImage(inputPath, platform) {
  const ext = path.extname(inputPath);
  const outDir = path.dirname(inputPath);
  const outFileName = `processed_${Date.now()}_${platform}${platform === 'instagram' ? '.jpg' : '.webp'}`;
  const outputPath = path.join(outDir, outFileName);

  console.log(`Processing image for ${platform}: ${inputPath}`);

  try {
    if (platform === 'instagram') {
      // Instagram preferred format: JPEG, 4:5 or 1:1 aspect ratio, max 1080px width
      await sharp(inputPath)
        .resize(1080, 1350, {
          fit: 'contain',
          background: { r: 255, g: 255, b: 255, alpha: 1 } // Pad with white background
        })
        .jpeg({ quality: 90 })
        .toFile(outputPath);
    } else {
      // Custom Web CMS: WebP, optimized, max 1200px width
      await sharp(inputPath)
        .resize({ width: 1200, withoutEnlargement: true })
        .webp({ quality: 80 })
        .toFile(outputPath);
    }
    return outputPath;
  } catch (err) {
    console.error('Image processing failed, falling back to original file:', err);
    return inputPath;
  }
}

export async function processVideo(inputPath, platform) {
  if (!isFfmpegAvailable) {
    console.log('Skipping video processing because FFmpeg is not installed.');
    return inputPath;
  }

  const outDir = path.dirname(inputPath);
  const outFileName = `processed_${Date.now()}_${platform}.mp4`;
  const outputPath = path.join(outDir, outFileName);

  console.log(`Processing video for ${platform}: ${inputPath}`);

  return new Promise((resolve) => {
    // Basic verification: Let's extract metadata to see if it needs cropping/trimming
    ffmpeg.ffprobe(inputPath, (err, metadata) => {
      if (err) {
        console.error('Failed to probe video metadata, skipping processing:', err);
        return resolve(inputPath);
      }

      const stream = metadata.streams.find(s => s.codec_type === 'video');
      if (!stream) {
        return resolve(inputPath);
      }

      const { width, height } = stream;
      const duration = metadata.format.duration;
      const isVertical = height > width;

      let command = ffmpeg(inputPath);

      if (platform === 'instagram' || platform === 'youtube_shorts') {
        // Reels & Shorts require 9:16 vertical videos.
        // Shorts must be < 60s. Reels should ideally be < 90s.
        
        let filter = [];
        if (!isVertical) {
          console.log('Video is landscape. Converting to vertical with blurred background padding...');
          // FFmpeg filter: Blur pad background + overlay centered original scaled video
          filter = [
            'split[original][copy]',
            '[copy]scale=1080:1920,boxblur=20:20[blurred]',
            '[original]scale=1080:-1[scaled]',
            '[blurred][scaled]overlay=(main_w-overlay_w)/2:(main_h-overlay_h)/2'
          ];
          command = command.videoFilters(filter);
        }

        // Clip YouTube Shorts or Instagram Reels to 60s/90s if too long
        const maxDuration = platform === 'youtube_shorts' ? 59 : 90;
        if (duration > maxDuration) {
          console.log(`Video duration (${duration}s) exceeds platform limit. Clipping to first ${maxDuration} seconds.`);
          command = command.duration(maxDuration);
        }

        command
          .output(outputPath)
          .videoCodec('libx264')
          .audioCodec('aac')
          .audioBitrate('128k')
          .videoBitrate('2500k') // High quality but reasonable file size
          .on('end', () => {
            console.log(`Video successfully formatted for ${platform}`);
            resolve(outputPath);
          })
          .on('error', (ffmpegErr) => {
            console.error('FFmpeg processing error:', ffmpegErr);
            resolve(inputPath); // Fallback
          })
          .run();
      } else {
        // Standard YouTube upload: Keep original size, compress if needed.
        // We will just do a lightweight copy/compress to save bandwidth.
        if (metadata.format.size > 100 * 1024 * 1024) { // > 100MB
          console.log('Video is large, compressing for Standard YouTube upload...');
          command
            .output(outputPath)
            .videoCodec('libx264')
            .addOption('-crf', '28') // Compress to smaller size
            .audioCodec('aac')
            .on('end', () => {
              console.log('Video compressed successfully.');
              resolve(outputPath);
            })
            .on('error', (ffmpegErr) => {
              console.error('Compression failed:', ffmpegErr);
              resolve(inputPath);
            })
            .run();
        } else {
          console.log('Video file size is small, skipping compression.');
          resolve(inputPath);
        }
      }
    });
  });
}
