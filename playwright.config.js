import { defineConfig } from '@playwright/test';
export default defineConfig({testDir:'./tests',timeout:120000,workers:1,use:{baseURL:process.env.BASE_URL||'http://127.0.0.1:5180',headless:true},webServer:process.env.BASE_URL?undefined:{command:'npm run dev -- --port 5180',url:'http://127.0.0.1:5180',reuseExistingServer:false},reporter:'list'});
