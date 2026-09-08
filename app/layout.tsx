import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "ბათუმის სახლში — მაგიდის დაჯავშნა",
  description: "აირჩიეთ მაგიდა, მენიუ და დაგეგმეთ თქვენი საღამო.",
  other: {
    "codex-preview": "development",
  },
  icons: {
    icon: "/favicon.svg",
    shortcut: "/favicon.svg",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ka">
      <body className="antialiased">{children}</body>
    </html>
  );
}
