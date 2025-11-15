/**
 * Script to create multiple clients for multi-tenant setup
 * Usage: node src/scripts/createClients.js
 */

import clientService from '../services/clientService.js';
import dotenv from 'dotenv';

dotenv.config();

async function createClients() {
  const clients = [
    { name: 'Client 1', apiUrl: 'https://client1.example.com' },
    { name: 'Client 2', apiUrl: 'https://client2.example.com' },
    { name: 'Client 3', apiUrl: 'https://client3.example.com' },
    { name: 'Client 4', apiUrl: 'https://client4.example.com' },
    { name: 'Client 5', apiUrl: 'https://client5.example.com' },
    { name: 'Client 6', apiUrl: 'https://client6.example.com' },
    { name: 'Client 7', apiUrl: 'https://client7.example.com' },
    { name: 'Client 8', apiUrl: 'https://client8.example.com' },
    { name: 'Client 9', apiUrl: 'https://client9.example.com' },
    { name: 'Client 10', apiUrl: 'https://client10.example.com' },
  ];

  console.log('🚀 Creating clients...\n');

  const results = [];

  for (const clientData of clients) {
    try {
      const client = await clientService.createClient(clientData.name, clientData.apiUrl);
      results.push(client);
      console.log(`✅ Created: ${client.name}`);
      console.log(`   Client ID: ${client.clientId}`);
      console.log(`   API Key: ${client.apiKey}`);
      console.log(`   API URL: ${client.apiUrl || 'N/A'}\n`);
    } catch (error) {
      console.error(`❌ Failed to create ${clientData.name}:`, error.message);
    }
  }

  console.log('\n📊 Summary:');
  console.log(`   Created: ${results.length} clients`);
  console.log('\n💾 Save these API keys securely!');
  console.log('\n📋 Client Credentials:');
  results.forEach((client, index) => {
    console.log(`\n${index + 1}. ${client.name}`);
    console.log(`   Client ID: ${client.clientId}`);
    console.log(`   API Key: ${client.apiKey}`);
    console.log(`   API URL: ${client.apiUrl || 'N/A'}`);
  });

  process.exit(0);
}

createClients().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});

