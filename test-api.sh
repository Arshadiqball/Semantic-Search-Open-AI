#!/bin/bash

# Semantic Job Matcher - API Test Script
# This script tests the API endpoints

API_BASE="http://localhost:3000"

echo "╔══════════════════════════════════════════════════════════╗"
echo "║   Semantic Job Matcher - API Test                       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test health endpoint
echo -e "${BLUE}Testing health endpoint...${NC}"
response=$(curl -s "${API_BASE}/health")
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Server is running${NC}"
    echo "Response: $response"
else
    echo -e "${RED}✗ Server is not responding${NC}"
    echo "Make sure the server is running: npm start"
    exit 1
fi

echo ""

# Test get all jobs
echo -e "${BLUE}Testing get all jobs...${NC}"
response=$(curl -s "${API_BASE}/api/jobs")
job_count=$(echo $response | grep -o '"count":[0-9]*' | grep -o '[0-9]*')
if [ ! -z "$job_count" ]; then
    echo -e "${GREEN}✓ Found $job_count jobs${NC}"
else
    echo -e "${RED}✗ No jobs found${NC}"
    echo "Run: npm run seed"
fi

echo ""

# Test upload resume
echo -e "${BLUE}Testing resume upload...${NC}"
if [ -f "$1" ]; then
    echo "Uploading: $1"
    response=$(curl -s -X POST "${API_BASE}/api/upload-resume" \
        -F "resume=@$1" \
        -F "threshold=0.5" \
        -F "limit=5")
    
    success=$(echo $response | grep -o '"success":true')
    if [ ! -z "$success" ]; then
        echo -e "${GREEN}✓ Resume uploaded successfully${NC}"
        echo ""
        echo "Response:"
        echo $response | python3 -m json.tool 2>/dev/null || echo $response
    else
        echo -e "${RED}✗ Upload failed${NC}"
        echo "Response: $response"
    fi
else
    echo -e "${RED}✗ No resume file provided${NC}"
    echo ""
    echo "Usage: ./test-api.sh /path/to/resume.pdf"
    echo ""
    echo "Or test without resume upload:"
    echo "  curl ${API_BASE}/health"
    echo "  curl ${API_BASE}/api/jobs"
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   API Test Complete                                      ║"
echo "╚══════════════════════════════════════════════════════════╝"

