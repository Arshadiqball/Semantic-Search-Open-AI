/**
 * Migration: 002_add_analytics_columns
 * Description: Adds email and ip_address columns to resumes table for analytics tracking
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Check if columns already exist before adding
  const checkEmail = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='email';
  `);
  
  const checkIP = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='ip_address';
  `);

  if (checkEmail.rows.length === 0) {
    await client.query(`
      ALTER TABLE resumes 
      ADD COLUMN email VARCHAR(255);
    `);
    console.log('  ✓ Added email column to resumes table');
  } else {
    console.log('  ⏭️  email column already exists');
  }

  if (checkIP.rows.length === 0) {
    await client.query(`
      ALTER TABLE resumes 
      ADD COLUMN ip_address VARCHAR(45);
    `);
    console.log('  ✓ Added ip_address column to resumes table');
  } else {
    console.log('  ⏭️  ip_address column already exists');
  }
};

export const down = async (client) => {
  // Check if columns exist before dropping
  const checkEmail = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='email';
  `);
  
  const checkIP = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='ip_address';
  `);

  if (checkEmail.rows.length > 0) {
    await client.query('ALTER TABLE resumes DROP COLUMN IF EXISTS email;');
    console.log('  ✓ Removed email column from resumes table');
  }

  if (checkIP.rows.length > 0) {
    await client.query('ALTER TABLE resumes DROP COLUMN IF EXISTS ip_address;');
    console.log('  ✓ Removed ip_address column from resumes table');
  }
};

