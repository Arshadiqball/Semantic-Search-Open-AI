FROM node:18-alpine

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy application files
COPY src ./src
COPY .env* ./

# Create uploads directory
RUN mkdir -p uploads

# Expose port
EXPOSE 3000

# Start application
CMD ["npm", "start"]

