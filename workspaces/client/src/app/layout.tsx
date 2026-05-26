import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'ts-php-type-bridge',
  description: 'Sharing TypeScript types with PHP via AST and PHP-Parser',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
