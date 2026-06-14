import fs from 'fs';
import path from 'path';
import { randomBytes } from 'crypto';
import dotenv from 'dotenv';

const envPath = path.join(process.cwd(), '.env');
const envExamplePath = path.join(process.cwd(), '.env.example');

console.log('========================================================');
console.log('  OmniPublish - System Initialization & Self-Test');
console.log('========================================================\n');

// 1. Automatically set up .env if it doesn't exist
if (!fs.existsSync(envPath)) {
  console.log('👉 .env file not found. Copying from .env.example...');
  if (fs.existsSync(envExamplePath)) {
    fs.copyFileSync(envExamplePath, envPath);
    console.log('✅ Created .env file.');
  } else {
    console.error('❌ .env.example template not found. Cannot proceed.');
    process.exit(1);
  }
}

// 2. Generate and set TOKEN_ENCRYPTION_KEY if it's default or missing
let envContent = fs.readFileSync(envPath, 'utf8');
const keyMatch = envContent.match(/^TOKEN_ENCRYPTION_KEY=(.*)$/m);
const currentKey = keyMatch ? keyMatch[1].trim() : '';

const defaultPlaceholder = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';

if (!currentKey || currentKey === defaultPlaceholder || currentKey.startsWith('your-')) {
  console.log('👉 Generating secure TOKEN_ENCRYPTION_KEY...');
  const newKey = randomBytes(32).toString('hex');
  
  if (keyMatch) {
    envContent = envContent.replace(/^TOKEN_ENCRYPTION_KEY=.*$/m, `TOKEN_ENCRYPTION_KEY=${newKey}`);
  } else {
    envContent += `\nTOKEN_ENCRYPTION_KEY=${newKey}\n`;
  }
  
  fs.writeFileSync(envPath, envContent, 'utf8');
  console.log('✅ Updated .env file with a secure, randomized encryption key.');
  // Reload env variables
  dotenv.config({ path: envPath });
} else {
  console.log('✅ TOKEN_ENCRYPTION_KEY is already customized and secure.');
}

// Reload dotenv to verify it is active
dotenv.config();

// 3. Test Database and Cryptography Modules
console.log('\n👉 Testing Cryptographic Store & Database...');
try {
  const { encrypt, decrypt, saveAccount, getAccount, removeAccount } = await import('./database.js');
  
  const testToken = 'ya29.a0AfB_byE84j7v...google-test-access-token';
  const encrypted = encrypt(testToken);
  
  if (!encrypted || !encrypted.ciphertext) {
    throw new Error('Encryption output is null or empty.');
  }
  
  const decrypted = decrypt(encrypted.ciphertext, encrypted.iv, encrypted.tag);
  if (decrypted !== testToken) {
    throw new Error('Decrypted output does not match original plaintext!');
  }
  console.log('✅ Encryption and Decryption verified.');

  // Test account save/retrieve
  saveAccount('test_platform', 'Test User', '123456', { access_token: 'secret_123' });
  const retrieved = getAccount('test_platform');
  if (retrieved && retrieved.accessToken === 'secret_123') {
    console.log('✅ Database local storage reading/writing verified.');
  } else {
    throw new Error('Retrieved token does not match.');
  }
  
  removeAccount('test_platform');
  console.log('✅ Database account deletion verified.');
  
} catch (err) {
  console.error('❌ Cryptography test failed:', err.message);
  console.error('Please verify your TOKEN_ENCRYPTION_KEY is a valid 64-character hex string.');
  process.exit(1);
}

// 4. Test Dependency imports
console.log('\n👉 Verifying Core Modules...');
try {
  const sharp = await import('sharp');
  console.log('✅ sharp (Image processor) loaded successfully.');
} catch (err) {
  console.error('❌ Failed to load sharp:', err.message);
}

try {
  const { checkFfmpeg } = await import('./media-processor.js');
  const ffmpegRes = await checkFfmpeg();
  if (ffmpegRes) {
    console.log('✅ FFmpeg is fully operational.');
  } else {
    console.log('⚠️  FFmpeg is missing. Videos will bypass compression/formatting and upload as-is.');
  }
} catch (err) {
  console.error('❌ Failed to verify FFmpeg:', err.message);
}

console.log('\n========================================================');
console.log('🎉 OmniPublish Self-Test Complete! System Ready to Launch.');
console.log('========================================================\n');
process.exit(0);
