#!/bin/bash

# Semantic Job Matcher - Setup Script
# This script helps you set up the project quickly

echo "╔══════════════════════════════════════════════════════════╗"
echo "║   Semantic Job Matcher - Setup Script                   ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 18+ first."
    echo "   Visit: https://nodejs.org/"
    exit 1
fi

echo "✓ Node.js $(node --version) found"

# Check if PostgreSQL is installed
if ! command -v psql &> /dev/null; then
    echo "❌ PostgreSQL is not installed."
    echo "   macOS: brew install postgresql@14"
    echo "   Ubuntu: sudo apt-get install postgresql"
    exit 1
fi

echo "✓ PostgreSQL found"

# Check if .env file exists
if [ ! -f .env ]; then
    echo ""
    echo "⚙️  Creating .env file..."
    cat > .env << 'EOF'
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here

# Database Configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME=semantic_job_matcher
DB_USER=postgres
DB_PASSWORD=

# Server Configuration
PORT=3000
NODE_ENV=development

# Upload Configuration
MAX_FILE_SIZE=5242880
EOF
    echo "✓ .env file created"
    echo ""
    echo "⚠️  IMPORTANT: Edit .env file and add your OpenAI API key and database password"
    echo "   Get your OpenAI key from: https://platform.openai.com/api-keys"
    echo ""
    read -p "Press Enter after you've updated the .env file..."
else
    echo "✓ .env file already exists"
fi

# Install dependencies
echo ""
echo "📦 Installing dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ Failed to install dependencies"
    exit 1
fi

echo "✓ Dependencies installed"

# Check database connection
echo ""
echo "🔍 Checking database connection..."
DB_NAME=$(grep DB_NAME .env | cut -d '=' -f2)
DB_USER=$(grep DB_USER .env | cut -d '=' -f2)

# Try to connect to PostgreSQL
if psql -U $DB_USER -lqt 2>/dev/null | cut -d \| -f 1 | grep -qw $DB_NAME; then
    echo "✓ Database '$DB_NAME' exists"
else
    echo "⚙️  Creating database '$DB_NAME'..."
    createdb -U $DB_USER $DB_NAME 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✓ Database created"
    else
        echo "⚠️  Could not create database automatically."
        echo "   Please create it manually:"
        echo "   psql -U $DB_USER"
        echo "   CREATE DATABASE $DB_NAME;"
    fi
fi

# Initialize database schema
echo ""
echo "🗄️  Initializing database schema..."
npm run init-db

if [ $? -ne 0 ]; then
    echo "❌ Failed to initialize database"
    echo "   Make sure pgvector is installed:"
    echo "   https://github.com/pgvector/pgvector"
    exit 1
fi

echo "✓ Database schema initialized"

# Seed sample jobs
echo ""
read -p "📊 Do you want to seed sample jobs? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🌱 Seeding sample jobs..."
    npm run seed
    if [ $? -eq 0 ]; then
        echo "✓ Sample jobs seeded"
    else
        echo "⚠️  Failed to seed jobs (you can try again later with: npm run seed)"
    fi
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   🎉 Setup Complete!                                     ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "To start the server:"
echo "  npm start         (production)"
echo "  npm run dev       (development with auto-reload)"
echo ""
echo "Test the API:"
echo "  1. Open test-client.html in your browser"
echo "  2. Or use curl:"
echo "     curl -X POST http://localhost:3000/api/upload-resume \\"
echo "       -F \"resume=@your-resume.pdf\""
echo ""
echo "Happy job matching! 🎯"

