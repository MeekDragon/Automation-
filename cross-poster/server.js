import express from 'express';
import cors from 'cors';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import dotenv from 'dotenv';
import axios from 'axios';
import { google } from 'googleapis';
import { createHmac, randomBytes } from 'crypto';

import {
  saveAccount,
  getAccount,
  removeAccount,
  readDb,
  writeDb,
  createJob,
  updateJobStatus,
  updateJobDestination,
  addLog,
  getJobs,
  getLogs,
  getUserByUsername,
  getUserById,
  getUsers,
  createUser,
  deleteUser,
  verifyPassword,
  getSettings,
  saveSettings
} from './database.js';

import { processImage, processVideo } from './media-processor.js';
import { uploadToYouTube, uploadToInstagram, uploadToWordPress } from './uploader.js';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Session Secret for JWT-like Cookie Auth
const SESSION_SECRET = process.env.SESSION_SECRET || randomBytes(32).toString('hex');

// Setup directories
const UPLOADS_DIR = path.join(process.cwd(), 'uploads');
if (!fs.existsSync(UPLOADS_DIR)) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(process.cwd(), 'public')));

// Configure Multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, UPLOADS_DIR);
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1e9);
    cb(null, uniqueSuffix + path.extname(file.originalname));
  }
});
const upload = multer({ storage });

// Helper: Date & Time Formatter
function formatDateTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const options = { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit', 
    hour12: true 
  };
  return date.toLocaleString('en-US', options);
}

// ----------------------------------------------------
// Authentication Helpers (HMAC-SHA256 Token Session)
// ----------------------------------------------------
function createToken(userId, role) {
  const expires = Date.now() + 30 * 24 * 60 * 60 * 1000; // 30 days
  const data = `${userId}.${role}.${expires}`;
  const signature = createHmac('sha256', SESSION_SECRET).update(data).digest('hex');
  return `${data}.${signature}`;
}

function verifyToken(token) {
  if (!token) return null;
  const parts = token.split('.');
  if (parts.length !== 4) return null;
  
  const [userId, role, expires, signature] = parts;
  if (Date.now() > parseInt(expires)) return null; // Expired
  
  const checkData = `${userId}.${role}.${expires}`;
  const checkSignature = createHmac('sha256', SESSION_SECRET).update(checkData).digest('hex');
  
  if (checkSignature !== signature) return null; // Invalid signature
  
  return { userId, role };
}

// Authentication Middleware
function authenticateToken(req, res, next) {
  // Parse Cookie manually
  const cookies = req.headers.cookie 
    ? Object.fromEntries(req.headers.cookie.split(';').map(c => c.trim().split('='))) 
    : {};
    
  const token = cookies.session_token || req.headers.authorization?.split(' ')[1];
  
  const payload = verifyToken(token);
  if (!payload) {
    return res.status(401).json({ error: 'Unauthorized: Session expired or invalid' });
  }
  
  req.user = payload;
  next();
}

// Role Check Middleware
function requireRole(allowedRoles) {
  return (req, res, next) => {
    if (!req.user || !allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Forbidden: Insufficient permissions' });
    }
    next();
  };
}

// ----------------------------------------------------
// Google OAuth Setup
// ----------------------------------------------------
const getGoogleOAuth2Client = (req) => {
  let redirectUri = process.env.GOOGLE_REDIRECT_URI;
  if (req) {
    const host = req.get('host');
    if (host && !redirectUri.includes(host)) {
      redirectUri = `${req.protocol}://${host}/auth/youtube/callback`;
    }
  }
  return new google.auth.OAuth2(
    process.env.GOOGLE_CLIENT_ID,
    process.env.GOOGLE_CLIENT_SECRET,
    redirectUri
  );
};

// ----------------------------------------------------
// Auth Routes
// ----------------------------------------------------
app.post('/api/auth/login', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) {
    return res.status(400).json({ error: 'Username and password are required' });
  }

  const user = getUserByUsername(username);
  if (!user || !verifyPassword(password, user)) {
    return res.status(401).json({ error: 'Invalid username or password' });
  }

  // Generate Token & set HttpOnly cookie
  const token = createToken(user.id, user.role);
  res.setHeader('Set-Cookie', `session_token=${token}; HttpOnly; Path=/; Max-Age=2592000`);
  res.json({ success: true, user: { id: user.id, username: user.username, role: user.role } });
});

app.post('/api/auth/logout', (req, res) => {
  res.setHeader('Set-Cookie', 'session_token=; HttpOnly; Path=/; Max-Age=0');
  res.json({ success: true });
});

app.get('/api/auth/me', authenticateToken, (req, res) => {
  const user = getUserById(req.user.userId);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.json({ user: { id: user.id, username: user.username, role: user.role } });
});

// ----------------------------------------------------
// User Admin Routes (Superadmin Only)
// ----------------------------------------------------
app.get('/api/admin/users', authenticateToken, requireRole(['superadmin']), (req, res) => {
  res.json(getUsers());
});

app.post('/api/admin/users', authenticateToken, requireRole(['superadmin']), (req, res) => {
  const { username, password, role } = req.body;
  if (!username || !password || !role) {
    return res.status(400).json({ error: 'Username, password, and role are required' });
  }
  try {
    const user = createUser(username, password, role);
    res.json({ success: true, user });
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

app.delete('/api/admin/users/:id', authenticateToken, requireRole(['superadmin']), (req, res) => {
  try {
    deleteUser(req.params.id);
    res.json({ success: true, message: 'User deleted successfully' });
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

// ----------------------------------------------------
// Global Defaults / Settings Routes
// ----------------------------------------------------
app.get('/api/settings', authenticateToken, (req, res) => {
  res.json(getSettings());
});

app.post('/api/settings', authenticateToken, requireRole(['superadmin', 'admin']), (req, res) => {
  const updated = saveSettings(req.body);
  res.json({ success: true, settings: updated });
});

// ----------------------------------------------------
// Channels / Accounts Routes (Admins Only)
// ----------------------------------------------------
app.get('/api/accounts', authenticateToken, (req, res) => {
  try {
    const db = readDb();
    const accountsInfo = {};
    const platforms = ['youtube', 'instagram', 'wordpress'];
    
    platforms.forEach(p => {
      const acc = db.accounts[p];
      accountsInfo[p] = acc ? {
        linked: true,
        accountName: acc.accountName,
        platformId: acc.platformId,
        linkedAt: acc.linkedAt
      } : { linked: false };
    });
    
    res.json(accountsInfo);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

app.post('/api/accounts/unlink', authenticateToken, requireRole(['superadmin', 'admin']), (req, res) => {
  const { platform } = req.body;
  if (!platform) {
    return res.status(400).json({ error: 'Platform is required' });
  }
  try {
    removeAccount(platform);
    res.json({ success: true, message: `${platform} unlinked successfully` });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});


app.get('/auth/youtube', authenticateToken, requireRole(['superadmin', 'admin']), (req, res) => {
  try {
    const oauth2Client = getGoogleOAuth2Client(req);
    const url = oauth2Client.generateAuthUrl({
      access_type: 'offline',
      prompt: 'consent',
      scope: [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly'
      ]
    });
    res.redirect(url);
  } catch (error) {
    res.status(500).send('OAuth configuration error. Verify environment variables.');
  }
});

app.get('/auth/youtube/callback', async (req, res) => {
  const { code } = req.query;
  if (!code) {
    return res.redirect('/?error=youtube_auth_cancelled');
  }

  try {
    const oauth2Client = getGoogleOAuth2Client(req);
    const { tokens } = await oauth2Client.getToken(code);
    oauth2Client.setCredentials(tokens);

    const youtube = google.youtube({ version: 'v3', auth: oauth2Client });
    const channelsRes = await youtube.channels.list({
      part: 'snippet',
      mine: true
    });

    if (!channelsRes.data.items || channelsRes.data.items.length === 0) {
      throw new Error('No YouTube channel found.');
    }

    const channelName = channelsRes.data.items[0].snippet.title;
    const channelId = channelsRes.data.items[0].id;

    saveAccount('youtube', channelName, channelId, tokens);
    res.redirect('/?success=youtube');
  } catch (error) {
    res.redirect(`/?error=youtube_auth_failed&msg=${encodeURIComponent(error.message)}`);
  }
});

app.get('/auth/instagram', authenticateToken, requireRole(['superadmin', 'admin']), (req, res) => {
  const appId = process.env.FACEBOOK_APP_ID;
  let redirectUri = process.env.FACEBOOK_REDIRECT_URI;
  const host = req.get('host');
  if (host && !redirectUri.includes(host)) {
    redirectUri = `${req.protocol}://${host}/auth/instagram/callback`;
  }
  if (!appId || !process.env.FACEBOOK_APP_SECRET) {
    return res.status(500).send('Facebook OAuth credentials not configured in .env');
  }
  const url = `https://www.facebook.com/v19.0/dialog/oauth?client_id=${appId}&redirect_uri=${encodeURIComponent(redirectUri)}&scope=instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement`;
  res.redirect(url);
});

app.get('/auth/instagram/callback', async (req, res) => {
  const { code } = req.query;
  if (!code) {
    return res.redirect('/?error=instagram_auth_cancelled');
  }

  try {
    const appId = process.env.FACEBOOK_APP_ID;
    const appSecret = process.env.FACEBOOK_APP_SECRET;
    let redirectUri = process.env.FACEBOOK_REDIRECT_URI;
    const host = req.get('host');
    if (host && !redirectUri.includes(host)) {
      redirectUri = `${req.protocol}://${host}/auth/instagram/callback`;
    }

    const tokenRes = await axios.get('https://graph.facebook.com/v19.0/oauth/access_token', {
      params: { client_id: appId, redirect_uri: redirectUri, client_secret: appSecret, code }
    });
    const shortLivedToken = tokenRes.data.access_token;

    const longLivedRes = await axios.get('https://graph.facebook.com/v19.0/oauth/access_token', {
      params: { grant_type: 'fb_exchange_token', client_id: appId, client_secret: appSecret, fb_exchange_token: shortLivedToken }
    });
    const longLivedToken = longLivedRes.data.access_token;
    const expiresAt = Date.now() + 60 * 24 * 60 * 60 * 1000;

    const pagesRes = await axios.get('https://graph.facebook.com/v19.0/me/accounts', {
      params: { fields: 'instagram_business_account,name', access_token: longLivedToken }
    });

    const pages = pagesRes.data.data;
    const linkedPage = pages.find(p => p.instagram_business_account);

    if (!linkedPage) {
      throw new Error('No Instagram Business/Creator account found linked to Facebook page.');
    }

    const igAccountId = linkedPage.instagram_business_account.id;
    const pageName = linkedPage.name;

    saveAccount('instagram', `${pageName} (Insta)`, igAccountId, { access_token: longLivedToken }, expiresAt);
    res.redirect('/?success=instagram');
  } catch (error) {
    const msg = error.response?.data?.error?.message || error.message;
    res.redirect(`/?error=instagram_auth_failed&msg=${encodeURIComponent(msg)}`);
  }
});

app.post('/api/accounts/wordpress', authenticateToken, requireRole(['superadmin', 'admin']), async (req, res) => {
  const { url, username, appPassword } = req.body;
  if (!url || !username || !appPassword) {
    return res.status(400).json({ error: 'WordPress parameters required' });
  }

  try {
    let cleanUrl = url.trim().replace(/\/$/, '');
    if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
      cleanUrl = 'https://' + cleanUrl;
    }
    const credentials = Buffer.from(`${username}:${appPassword}`).toString('base64');
    
    await axios.get(`${cleanUrl}/wp-json/wp/v2/users/me`, {
      headers: { Authorization: `Basic ${credentials}` }
    });

    saveAccount('wordpress', `${username} @ ${cleanUrl.replace(/^https?:\/\//, '')}`, 'wordpress-api', {
      access_token: appPassword
    });

    res.json({ success: true, message: 'WordPress account linked successfully!' });
  } catch (error) {
    const msg = error.response?.data?.message || 'Verification failed.';
    res.status(401).json({ error: `WordPress Verification Failed: ${msg}` });
  }
});

// ----------------------------------------------------
// History & Logs Endpoints
// ----------------------------------------------------
app.get('/api/jobs', authenticateToken, (req, res) => {
  const user = getUserById(req.user.userId);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  
  const allJobs = getJobs();
  if (user.role === 'user') {
    // Standard user: return only jobs they created
    const filteredJobs = allJobs.filter(j => j.createdBy === user.username);
    return res.json(filteredJobs);
  }
  
  // Admin / Superadmin: return all jobs
  res.json(allJobs);
});

app.get('/api/logs', authenticateToken, (req, res) => {
  const { jobId } = req.query;
  const user = getUserById(req.user.userId);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  
  if (jobId) {
    // Verify job ownership
    const db = readDb();
    const job = db.jobs.find(j => j.id === jobId);
    if (!job) {
      return res.status(404).json({ error: 'Job not found' });
    }
    
    // Standard user role check
    if (user.role === 'user' && job.createdBy !== user.username) {
      return res.status(403).json({ error: 'Forbidden: You do not have permission to view logs for this job' });
    }
    
    return res.json(getLogs(jobId));
  }
  
  // If no jobId specified, standard user gets logs for their jobs only
  if (user.role === 'user') {
    const db = readDb();
    const userJobIds = new Set(db.jobs.filter(j => j.createdBy === user.username).map(j => j.id));
    const allLogs = getLogs();
    const filteredLogs = allLogs.filter(l => userJobIds.has(l.jobId));
    return res.json(filteredLogs);
  }
  
  res.json(getLogs());
});

// ----------------------------------------------------
// Publish Job Creator / Scheduler
// ----------------------------------------------------
app.post('/api/publish', authenticateToken, upload.single('media'), async (req, res) => {
  const { title, description, tags, scheduledAt } = req.body;
  const selectedPlatforms = JSON.parse(req.body.platforms || '[]');
  const file = req.file;

  // Read options overrides
  const platformOptions = {
    youtubePrivacy: req.body.youtubePrivacy || 'public',
    youtubeCategory: req.body.youtubeCategory || '22',
    wordpressStatus: req.body.wordpressStatus || 'publish'
  };

  if (!file) {
    return res.status(400).json({ error: 'Media file is required' });
  }

  if (selectedPlatforms.length === 0) {
    return res.status(400).json({ error: 'At least one target platform must be selected' });
  }

  const jobId = `job_${Date.now()}`;
  const parsedTags = tags ? tags.split(',').map(t => t.trim()).filter(Boolean) : [];

  // Determine current user
  const db = readDb();
  const creatorUser = db.users.find(u => u.id === req.user.userId);
  const creatorName = creatorUser ? creatorUser.username : 'Unknown';

  if (scheduledAt) {
    // Register Scheduled Job
    createJob(jobId, title, description, file.path, selectedPlatforms, creatorName, scheduledAt, platformOptions);
    addLog(jobId, 'system', `Post successfully scheduled for execution at: ${formatDateTime(scheduledAt)}`);
    return res.json({ success: true, jobId, message: `Publishing scheduled successfully for ${formatDateTime(scheduledAt)}` });
  }

  // Register Instant Job
  createJob(jobId, title, description, file.path, selectedPlatforms, creatorName, null, platformOptions);
  addLog(jobId, 'system', `Instant publishing pipeline started by user: ${creatorName}`);

  // Start background publisher
  processCrossPostingJob(jobId, file.path, { title, description, tags: parsedTags }, selectedPlatforms, platformOptions);

  res.json({ success: true, jobId, message: 'Publishing job enqueued in background' });
});

// ----------------------------------------------------
// Background Async Publisher
// ----------------------------------------------------
async function processCrossPostingJob(jobId, rawFilePath, metadata, platforms, platformOptions = {}) {
  updateJobStatus(jobId, 'PROCESSING');
  let currentFile = rawFilePath;
  const filesToCleanup = [];

  for (const platform of platforms) {
    addLog(jobId, platform, `Starting delivery to ${platform}...`);
    updateJobDestination(jobId, platform, { status: 'PROCESSING' });

    try {
      const targetAccountKey = platform.startsWith('youtube') ? 'youtube' : (platform.startsWith('instagram') ? 'instagram' : platform);
      const account = getAccount(targetAccountKey);

      if (!account) {
        throw new Error(`Platform account '${targetAccountKey}' is not linked.`);
      }

      addLog(jobId, platform, 'Analyzing media assets format and parameters...');
      let processedPath = currentFile;
      const isVideo = ['.mp4', '.mov', '.avi'].includes(path.extname(rawFilePath).toLowerCase());

      if (isVideo) {
        processedPath = await processVideo(rawFilePath, platform);
      } else {
        processedPath = await processImage(rawFilePath, platform);
      }

      if (processedPath !== rawFilePath) {
        filesToCleanup.push(processedPath);
      }

      let permalink = null;

      if (platform === 'youtube_video') {
        permalink = await uploadToYouTube(jobId, account, processedPath, metadata, 'youtube_video', platformOptions);
      } else if (platform === 'youtube_shorts') {
        permalink = await uploadToYouTube(jobId, account, processedPath, metadata, 'youtube_shorts', platformOptions);
      } else if (platform === 'youtube_post') {
        permalink = await uploadToYouTube(jobId, account, processedPath, metadata, 'youtube_post', platformOptions);
      } else if (platform === 'youtube') {
        // Fallback for legacy jobs
        permalink = await uploadToYouTube(jobId, account, processedPath, metadata, 'youtube_video', platformOptions);
      } else if (platform.startsWith('instagram')) {
        permalink = await uploadToInstagram(jobId, account, processedPath, metadata.description, platform, platformOptions);
      } else if (platform === 'wordpress') {
        permalink = await uploadToWordPress(jobId, account, processedPath, metadata, platformOptions);
      }

      // Update Database with dynamic permalink URL
      updateJobDestination(jobId, platform, {
        status: 'COMPLETED',
        externalId: permalink
      });
      addLog(jobId, platform, `Successfully published! View Post: ${permalink}`, 'success');

    } catch (err) {
      console.error(`Error cross-posting to ${platform}:`, err);
      const errorMessage = err.message || 'Unknown error';
      updateJobDestination(jobId, platform, {
        status: 'FAILED',
        error: errorMessage
      });
      addLog(jobId, platform, `Failed: ${errorMessage}`, 'error');
    }
  }

  // Cleanup temp files
  try {
    for (const tempFile of filesToCleanup) {
      if (fs.existsSync(tempFile)) {
        fs.unlinkSync(tempFile);
      }
    }
  } catch (cleanupErr) {
    console.error('Error cleaning up temp files:', cleanupErr);
  }
}

// ----------------------------------------------------
// Background Scheduler Scanner Loop (Runs every 30 seconds)
// ----------------------------------------------------
setInterval(() => {
  try {
    const db = readDb();
    const now = new Date();
    
    // Find scheduled jobs that are past due
    const scheduledJobs = db.jobs.filter(j => j.status === 'SCHEDULED' && j.scheduledAt && new Date(j.scheduledAt) <= now);
    
    if (scheduledJobs.length > 0) {
      console.log(`[Scheduler] Scanning found ${scheduledJobs.length} jobs to process.`);
    }

    for (const job of scheduledJobs) {
      console.log(`[Scheduler] Triggering job ${job.id} ("${job.title}")`);
      addLog(job.id, 'system', `Scheduled posting time reached. Initiating background delivery...`);
      
      // Update statuses to transition out of SCHEDULED
      job.status = 'PENDING';
      Object.keys(job.destinations).forEach(p => {
        job.destinations[p].status = 'PENDING';
      });
      
      // Save state to file first
      writeDb(db);
      
      // Trigger background upload
      const parsedTags = job.tags ? job.tags.split(',').map(t => t.trim()).filter(Boolean) : [];
      processCrossPostingJob(
        job.id, 
        job.mediaPath, 
        { title: job.title, description: job.description, tags: parsedTags }, 
        Object.keys(job.destinations), 
        job.platformOptions
      );
    }
  } catch (err) {
    console.error('[Scheduler Scanner Error]:', err);
  }
}, 30000);

// Start Server
app.listen(PORT, () => {
  console.log(`========================================================`);
  console.log(`  OmniPublish Server is running at http://localhost:${PORT}`);
  console.log(`  26 Users seeded. Log in with Superadmin credentials.`);
  console.log(`========================================================`);
});
