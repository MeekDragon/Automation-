import fs from 'fs';
import path from 'path';
import { readDb, writeDb, hashPassword } from './database.js';

console.log('========================================================');
console.log('  OmniPublish - User Seeder Tool');
console.log('========================================================\n');

try {
  const db = readDb();
  
  // Clear existing users
  db.users = [];
  console.log('🧹 Clearing existing users table...');

  // 1. Seed 1 Superadmin
  const superPwd = hashPassword('superpassword');
  db.users.push({
    id: 'user_super',
    username: 'superadmin',
    passwordHash: superPwd.hash,
    salt: superPwd.salt,
    role: 'superadmin'
  });
  console.log('👤 Seeded Superadmin: "superadmin" / Password: "superpassword"');

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
  console.log('👥 Seeded 5 Admins: "admin1" through "admin5" / Password: "adminpassword"');

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
  console.log('👥 Seeded 20 Standard Users: "user1" through "user20" / Password: "userpassword"');

  writeDb(db);
  console.log('\n✅ User database successfully seeded!');
  console.log('========================================================\n');
  process.exit(0);

} catch (err) {
  console.error('❌ Failed to seed users:', err.message);
  process.exit(1);
}
