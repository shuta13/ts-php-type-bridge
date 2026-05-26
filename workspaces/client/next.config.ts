import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  // Allow Next.js to compile the TypeScript source of the types-bridge workspace.
  transpilePackages: ['types-bridge'],
};

export default nextConfig;
