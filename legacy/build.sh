#!/bin/bash

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
  npm install
fi

# Build Tailwind CSS
npm run build

echo "Build completed successfully"
