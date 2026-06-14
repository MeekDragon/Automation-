import fs from 'fs';
import path from 'path';
import { createCipheriv, createDecipheriv, randomBytes, pbkdf2Sync } from 'crypto';
import dotenv from 'dotenv';

dotenv.config();

const DB_FILE = path.join(process.cwd(), 'db.json');
const ALGORITHM = 'aes-256-gcm';
const ENCRYPTION_KEY = process.env.TOKEN_ENCRYPTION_KEY;

// Verify Encryption Key
function getSecretKey() {
  if (!ENCRYPTION_KEY) {
    throw new Error('TOKEN_ENCRYPTION_KEY is missing in your .env file.');
  }
  const key = Buffer.from(ENCRYPTION_KEY, 'hex');
  if (key.length !== 32) {
    throw new Error('TOKEN_ENCRYPTION_KEY must be a 32-byte hex string (64 characters).');
  }
  return key;
}

// Lowdb-like simple JSON helper
function initDb() {
  if (!fs.existsSync(DB_FILE)) {
    const defaultDb = {
      accounts: {}, // Mapped by platform: { accountName, platformId, accessToken, refreshToken, iv, tag... }
      jobs: [],     // Array of jobs
      logs: [],     // Array of global process logs
      users: [],    // Array of users: { id, username, passwordHash, salt, role }
      settings: {   // Default presets settings
        defaultTags: '',
        defaultDescription: '',
        selectedPlatforms: [],
        youtubePrivacy: 'public',
        youtubeCategory: '22',
        wordpressStatus: 'publish',
        instagramAspectRatio: '4:5'
      }
    };
    fs.writeFileSync(DB_FILE, JSON.stringify(defaultDb, null, 2), 'utf8');
  }
}

export function readDb() {
  initDb();
  const data = fs.readFileSync(DB_FILE, 'utf8');
  return JSON.parse(data);
}

export function writeDb(data) {
  fs.writeFileSync(DB_FILE, JSON.stringify(data, null, 2), 'utf8');
}

// AES-256-GCM Encryption
export function encrypt(text) {
  if (!text) return null;
  const key = getSecretKey();
  const iv = randomBytes(12);
  const cipher = createCipheriv(ALGORITHM, key, iv);
  
  let encrypted = cipher.update(text, 'utf8', 'hex');
  encrypted += cipher.final('hex');
  const tag = cipher.getAuthTag();
  
  return {
    ciphertext: encrypted,
    iv: iv.toString('hex'),
    tag: tag.toString('hex')
  };
}

// AES-256-GCM Decryption
export function decrypt(ciphertext, ivHex, tagHex) {
  if (!ciphertext || !ivHex || !tagHex) return null;
  const key = getSecretKey();
  const iv = Buffer.from(ivHex, 'hex');
  const tag = Buffer.from(tagHex, 'hex');
  
  const decipher = createDecipheriv(ALGORITHM, key, iv);
  decipher.setAuthTag(tag);
  
  let decrypted = decipher.update(ciphertext, 'hex', 'utf8');
  decrypted += decipher.final('utf8');
  return decrypted;
}

// ----------------------------------------------------
// Password Hashing (Built-in Cryptography pbkdf2)
// ----------------------------------------------------
export function hashPassword(password, salt = null) {
  const userSalt = salt || randomBytes(16).toString('hex');
  const hash = pbkdf2Sync(password, userSalt, 1000, 64, 'sha512').toString('hex');
  return { salt: userSalt, hash };
}

export function verifyPassword(password, user) {
  const { hash } = hashPassword(password, user.salt);
  return hash === user.passwordHash;
}

// ----------------------------------------------------
// Seeding & User Management
// ----------------------------------------------------
export function seedUsers() {
  const db = readDb();
  if (db.users && db.users.length > 0) return;

  db.users = [];

  // 1. Seed Superadmin
  const superPwd = hashPassword('superpassword');
  db.users.push({
    id: 'user_super',
    username: 'superadmin',
    passwordHash: superPwd.hash,
    salt: superPwd.salt,
    role: 'superadmin'
  });

  // 2. Seed 5 Admins
  for (let i = 1; i <= 5; i++) {
    const adminPwd = hashPassword('adminpassword');
    db.users.push({
      id: `user_admin_${i}`,
      username: `admin${i}`,
      passwordHash: adminPwd.hash,
      salt: adminPwd.salt,
      role: 'admin'
    });
  }

  // 3. Seed 20 Standard Users
  for (let i = 1; i <= 20; i++) {
    const userPwd = hashPassword('userpassword');
    db.users.push({
      id: `user_std_${i}`,
      username: `user${i}`,
      passwordHash: userPwd.hash,
      salt: userPwd.salt,
      role: 'user'
    });
  }

  writeDb(db);
  console.log('✅ Seeded 1 Superadmin, 5 Admins, and 20 Users successfully.');
}

// Seeding trigger
seedUsers();

export function getUserByUsername(username) {
  const db = readDb();
  return db.users.find(u => u.username.toLowerCase() === username.toLowerCase());
}

export function getUserById(id) {
  const db = readDb();
  return db.users.find(u => u.id === id);
}

export function getUsers() {
  const db = readDb();
  // Don't return hashes or salts to the API
  return db.users.map(u => ({ id: u.id, username: u.username, role: u.role }));
}

export function createUser(username, password, role) {
  const db = readDb();
  if (db.users.some(u => u.username.toLowerCase() === username.toLowerCase())) {
    throw new Error('Username already exists');
  }

  const { hash, salt } = hashPassword(password);
  const newUser = {
    id: `user_${Date.now()}`,
    username,
    passwordHash: hash,
    salt,
    role
  };

  db.users.push(newUser);
  writeDb(db);
  return { id: newUser.id, username: newUser.username, role: newUser.role };
}

export function deleteUser(id) {
  const db = readDb();
  const index = db.users.findIndex(u => u.id === id);
  if (index === -1) {
    throw new Error('User not found');
  }
  if (db.users[index].role === 'superadmin') {
    throw new Error('Cannot delete Superadmin');
  }
  db.users.splice(index, 1);
  writeDb(db);
}

// ----------------------------------------------------
// Settings Management
// ----------------------------------------------------
export function getSettings() {
  const db = readDb();
  // Fallback to defaults if settings doesn't exist
  if (!db.settings) {
    db.settings = {
      defaultTags: '',
      defaultDescription: '',
      selectedPlatforms: [],
      youtubePrivacy: 'public',
      youtubeCategory: '22',
      wordpressStatus: 'publish',
      instagramAspectRatio: '4:5'
    };
    writeDb(db);
  }
  return db.settings;
}

export function saveSettings(settings) {
  const db = readDb();
  db.settings = {
    ...db.settings,
    ...settings
  };
  writeDb(db);
  return db.settings;
}

// ----------------------------------------------------
// Manage Accounts
// ----------------------------------------------------
export function saveAccount(platform, accountName, platformId, credentials, expiresAt = null) {
  const db = readDb();
  
  const encryptedAccess = encrypt(credentials.access_token);
  const encryptedRefresh = credentials.refresh_token ? encrypt(credentials.refresh_token) : null;
  
  db.accounts[platform] = {
    accountName,
    platformId,
    accessToken: encryptedAccess.ciphertext,
    refreshToken: encryptedRefresh ? encryptedRefresh.ciphertext : null,
    iv: encryptedAccess.iv,
    tag: encryptedAccess.tag,
    refreshIv: encryptedRefresh ? encryptedRefresh.iv : null,
    refreshTag: encryptedRefresh ? encryptedRefresh.tag : null,
    expiresAt: expiresAt ? new Date(expiresAt).toISOString() : null,
    linkedAt: new Date().toISOString()
  };
  
  writeDb(db);
}

export function getAccount(platform) {
  const db = readDb();
  const account = db.accounts[platform];
  if (!account) return null;
  
  try {
    const accessToken = decrypt(account.accessToken, account.iv, account.tag);
    const refreshToken = account.refreshToken 
      ? decrypt(account.refreshToken, account.refreshIv, account.refreshTag) 
      : null;
      
    return {
      ...account,
      accessToken,
      refreshToken
    };
  } catch (err) {
    console.error(`Error decrypting credentials for ${platform}:`, err);
    throw new Error(`Failed to decrypt credentials for ${platform}. Check your TOKEN_ENCRYPTION_KEY.`);
  }
}

export function removeAccount(platform) {
  const db = readDb();
  delete db.accounts[platform];
  writeDb(db);
}

// ----------------------------------------------------
// Manage Jobs
// ----------------------------------------------------
export function createJob(id, title, description, mediaPath, platforms, createdBy, scheduledAt = null, platformOptions = {}) {
  const db = readDb();
  
  const destinations = {};
  platforms.forEach(p => {
    destinations[p] = {
      status: scheduledAt ? 'SCHEDULED' : 'PENDING',
      error: null,
      externalId: null
    };
  });

  const job = {
    id,
    title,
    description,
    mediaPath,
    status: scheduledAt ? 'SCHEDULED' : 'PENDING',
    scheduledAt: scheduledAt ? new Date(scheduledAt).toISOString() : null,
    createdBy, // Store username of creator
    createdAt: new Date().toISOString(),
    destinations,
    platformOptions // Store settings specific to this upload
  };
  
  db.jobs.unshift(job);
  writeDb(db);
  return job;
}

export function updateJobStatus(id, status) {
  const db = readDb();
  const job = db.jobs.find(j => j.id === id);
  if (job) {
    job.status = status;
    writeDb(db);
  }
}

export function updateJobDestination(id, platform, update) {
  const db = readDb();
  const job = db.jobs.find(j => j.id === id);
  if (job && job.destinations[platform]) {
    job.destinations[platform] = {
      ...job.destinations[platform],
      ...update
    };
    
    // Auto-update parent status
    const destStatuses = Object.values(job.destinations).map(d => d.status);
    if (destStatuses.every(s => s === 'COMPLETED')) {
      job.status = 'COMPLETED';
    } else if (destStatuses.some(s => s === 'FAILED') && !destStatuses.some(s => s === 'PENDING' || s === 'PROCESSING' || s === 'SCHEDULED')) {
      job.status = 'FAILED';
    } else if (destStatuses.some(s => s === 'PROCESSING')) {
      job.status = 'PROCESSING';
    }
    
    writeDb(db);
  }
}

export function getJobs(userId = null) {
  const db = readDb();
  return db.jobs;
}

// Logs
export function addLog(jobId, platform, message, type = 'info') {
  const db = readDb();
  const logEntry = {
    timestamp: new Date().toISOString(),
    jobId,
    platform,
    message,
    type
  };
  
  db.logs.unshift(logEntry);
  if (db.logs.length > 1000) {
    db.logs = db.logs.slice(0, 1000);
  }
  writeDb(db);
  return logEntry;
}

export function getLogs(jobId = null) {
  const db = readDb();
  if (jobId) {
    return db.logs.filter(l => l.jobId === jobId);
  }
  return db.logs;
}
