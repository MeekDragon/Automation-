import axios from 'axios';
import fs from 'fs';
import path from 'path';
import FormData from 'form-data';
import { google } from 'googleapis';
import { addLog } from './database.js';

// Helper to host local files temporarily so that Meta APIs can access them.
async function getPublicUrlForFile(jobId, platform, filePath) {
  addLog(jobId, platform, 'Uploading media to a temporary public URL for platform retrieval...');
  try {
    const form = new FormData();
    form.append('file', fs.createReadStream(filePath));

    // Upload to tmpfiles.org
    const response = await axios.post('https://tmpfiles.org/api/v1/upload', form, {
      headers: form.getHeaders(),
      maxContentLength: Infinity,
      maxBodyLength: Infinity
    });

    if (response.data && response.data.status === 'success') {
      const uploadUrl = response.data.data.url;
      const directUrl = uploadUrl.replace('https://tmpfiles.org/', 'https://tmpfiles.org/dl/');
      addLog(jobId, platform, `Temporary direct media URL generated: ${directUrl}`);
      return directUrl;
    } else {
      throw new Error('Failed to upload file to tmpfiles.org');
    }
  } catch (err) {
    console.error('Failed to upload to temp public URL:', err);
    throw new Error(`Failed to provision temporary public URL for media: ${err.message}`);
  }
}

// ----------------------------------------------------
// YouTube Uploader (with custom privacy & categories)
// ----------------------------------------------------
export async function uploadToYouTube(jobId, account, filePath, metadata, type = 'youtube_video', options = {}) {
  const platformName = type;

  if (type === 'youtube_post') {
    throw new Error('YouTube API v3 does not support publishing Community Posts (text/image updates) via third-party application credentials. To post live community posts, please use the YouTube web interface directly.');
  }

  addLog(jobId, platformName, 'Initializing YouTube Data API client...');

  const oauth2Client = new google.auth.OAuth2(
    process.env.GOOGLE_CLIENT_ID,
    process.env.GOOGLE_CLIENT_SECRET,
    process.env.GOOGLE_REDIRECT_URI
  );

  oauth2Client.setCredentials({
    access_token: account.accessToken,
    refresh_token: account.refreshToken
  });

  const youtube = google.youtube({
    version: 'v3',
    auth: oauth2Client
  });

  addLog(jobId, platformName, 'Uploading video stream directly to YouTube...');

  const fileSize = fs.statSync(filePath).size;
  const mediaStream = fs.createReadStream(filePath);

  const privacyStatus = options.youtubePrivacy || 'public';
  const categoryId = options.youtubeCategory || '22'; // Default: People & Blogs

  const requestBody = {
    snippet: {
      title: metadata.title,
      description: metadata.description + (type === 'youtube_shorts' ? ' #Shorts' : ''),
      tags: metadata.tags || [],
      categoryId: categoryId
    },
    status: {
      privacyStatus: privacyStatus,
      selfDeclaredMadeForKids: false
    }
  };

  const response = await youtube.videos.insert({
    part: ['snippet', 'status'],
    requestBody: requestBody,
    media: {
      body: mediaStream
    }
  }, {
    onUploadProgress: (evt) => {
      const progress = Math.round((evt.bytesRead / fileSize) * 100);
      addLog(jobId, platformName, `Uploading progress: ${progress}%`);
    }
  });

  const videoId = response.data.id;
  const videoUrl = type === 'youtube_shorts' 
    ? `https://youtube.com/shorts/${videoId}` 
    : `https://www.youtube.com/watch?v=${videoId}`;

  addLog(jobId, platformName, `Video successfully posted! Video URL: ${videoUrl}`, 'success');
  return videoUrl;
}

// ----------------------------------------------------
// Instagram Uploader (with custom aspect ratios & fetching permalinks)
// ----------------------------------------------------
export async function uploadToInstagram(jobId, account, filePath, caption, type = 'instagram_post', options = {}) {
  const platformName = type;



  const igAccountId = account.platformId;
  const accessToken = account.accessToken;

  // 1. Host file temporarily
  const publicMediaUrl = await getPublicUrlForFile(jobId, platformName, filePath);

  addLog(jobId, platformName, `Creating Instagram media container (${type.replace('instagram_', '').toUpperCase()})...`);

  // 2. Initialize Media Container on Meta Graph API
  const containerParams = {
    access_token: accessToken
  };

  if (type === 'instagram_story') {
    containerParams.media_type = 'STORIES';
    const ext = path.extname(filePath).toLowerCase();
    if (ext === '.mp4' || ext === '.mov') {
      containerParams.video_url = publicMediaUrl;
    } else {
      containerParams.image_url = publicMediaUrl;
    }
  } else if (type === 'instagram_reel') {
    containerParams.media_type = 'REELS';
    containerParams.video_url = publicMediaUrl;
    containerParams.share_to_feed = true;
    containerParams.caption = caption;
  } else {
    // instagram_post
    containerParams.caption = caption;
    const ext = path.extname(filePath).toLowerCase();
    if (ext === '.mp4' || ext === '.mov') {
      containerParams.media_type = 'VIDEO';
      containerParams.video_url = publicMediaUrl;
    } else {
      containerParams.image_url = publicMediaUrl;
    }
  }

  const containerRes = await axios.post(
    `https://graph.facebook.com/v19.0/${igAccountId}/media`,
    containerParams
  );

  const containerId = containerRes.data.id;
  addLog(jobId, platformName, `Container initialized. ID: ${containerId}. Waiting for Instagram to process...`);

  // 3. Poll Container Status
  let status = 'IN_PROGRESS';
  const pollUrl = `https://graph.facebook.com/v19.0/${containerId}?fields=status_code,failure_reason&access_token=${accessToken}`;
  const maxPolls = 15;
  let attempts = 0;

  while (status !== 'FINISHED' && attempts < maxPolls) {
    attempts++;
    addLog(jobId, platformName, `Polling processing status (Attempt ${attempts}/${maxPolls})...`);
    await new Promise(resolve => setTimeout(resolve, 10000));

    const statusRes = await axios.get(pollUrl);
    status = statusRes.data.status_code;

    if (status === 'FINISHED') {
      break;
    } else if (status === 'ERROR' || status === 'EXPIRED') {
      const reason = statusRes.data.failure_reason || 'Unknown processing error';
      throw new Error(`Instagram media processing failed: ${reason}`);
    }
  }

  if (status !== 'FINISHED') {
    throw new Error('Instagram media processing timed out.');
  }

  addLog(jobId, platformName, 'Processing complete. Publishing post now...');

  // 4. Publish the media container
  const publishRes = await axios.post(
    `https://graph.facebook.com/v19.0/${igAccountId}/media_publish`,
    {
      creation_id: containerId,
      access_token: accessToken
    }
  );

  const postId = publishRes.data.id;
  
  // 5. Query public permalink for the published post
  let postUrl = `https://instagram.com`;
  try {
    addLog(jobId, platformName, 'Fetching public permalink from Instagram...');
    const permalinkRes = await axios.get(`https://graph.facebook.com/v19.0/${postId}`, {
      params: {
        fields: 'permalink',
        access_token: accessToken
      }
    });
    if (permalinkRes.data && permalinkRes.data.permalink) {
      postUrl = permalinkRes.data.permalink;
    }
  } catch (permalinkErr) {
    console.warn('Failed to retrieve Instagram permalink, falling back to general profile link:', permalinkErr);
    // Fallback: construct standard link if profile handle is cached
    postUrl = `https://instagram.com`;
  }

  addLog(jobId, platformName, `Post published successfully! Link: ${postUrl}`, 'success');
  return postUrl;
}

// ----------------------------------------------------
// Web CMS (WordPress) Uploader (with post status option)
// ----------------------------------------------------
export async function uploadToWordPress(jobId, account, filePath, metadata, options = {}) {
  const platformName = 'wordpress';



  addLog(jobId, platformName, 'Connecting to WordPress REST API...');

  const wpUrl = process.env.WORDPRESS_URL;
  const username = process.env.WORDPRESS_USER;
  const appPassword = process.env.WORDPRESS_APP_PASSWORD;

  if (!wpUrl || !username || !appPassword) {
    throw new Error('WordPress environment variables are not fully configured in your .env file.');
  }

  const credentials = Buffer.from(`${username}:${appPassword}`).toString('base64');
  const headers = { Authorization: `Basic ${credentials}` };

  // 1. Upload Media
  addLog(jobId, platformName, 'Uploading media file to WordPress library...');
  const form = new FormData();
  form.append('file', fs.createReadStream(filePath));
  
  const mediaResponse = await axios.post(
    `${wpUrl}/wp-json/wp/v2/media`,
    form,
    { 
      headers: { 
        ...headers, 
        ...form.getHeaders() 
      },
      maxContentLength: Infinity,
      maxBodyLength: Infinity
    }
  );
  
  const mediaId = mediaResponse.data.id;
  const mediaSourceUrl = mediaResponse.data.source_url;
  const isVideo = mediaResponse.data.mime_type.startsWith('video/');

  addLog(jobId, platformName, `Media uploaded successfully. Media ID: ${mediaId}`);

  // 2. Draft or Publish Post
  const wpPostStatus = options.wordpressStatus || 'publish';
  addLog(jobId, platformName, `Creating new blog post (status: ${wpPostStatus})...`);

  let postContent = '';
  if (isVideo) {
    postContent = `
      <figure class="wp-block-video"><video controls src="${mediaSourceUrl}"></video></figure>
      <p>${metadata.description.replace(/\n/g, '<br>')}</p>
    `;
  } else {
    postContent = `
      <figure class="wp-block-image"><img src="${mediaSourceUrl}" alt="${metadata.title}"/></figure>
      <p>${metadata.description.replace(/\n/g, '<br>')}</p>
    `;
  }

  const postResponse = await axios.post(
    `${wpUrl}/wp-json/wp/v2/posts`,
    {
      title: metadata.title,
      content: postContent,
      status: wpPostStatus,
      featured_media: isVideo ? 0 : mediaId
    },
    { headers }
  );

  const postUrl = postResponse.data.link;
  addLog(jobId, platformName, `Blog post published successfully! Link: ${postUrl}`, 'success');
  return postUrl;
}
