import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  // Allow Next.js to compile the TypeScript source of the type-bridge workspace.
  transpilePackages: ['type-bridge'],
};

export default nextConfig;
