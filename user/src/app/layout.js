import { Inter } from "next/font/google";
import "./globals.css";
import Header from "@/components/Header/Header";
import Footer from "@/components/Footer/Footer";
import { CartProvider } from "@/context/CartContext";
import { MarketplaceAuthProvider } from "@/context/MarketplaceAuthContext";
import { ThemeProvider } from "@/components/ThemeProvider/ThemeProvider";

import ClientTracker from "@/components/ClientTracker/ClientTracker";

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  weight: ["300", "400", "500", "600", "700", "800", "900"],
});

export const metadata = {
  title: "Electava — Electronics Marketplace",
  description: "Electava helps engineers source components, compare manufacturers, and plan their builds.",
  keywords: "electronic components, semiconductors, resistors, capacitors, microcontrollers, PCB, Arduino, ESP32",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en" className={inter.variable} suppressHydrationWarning>
      <body>
        <ThemeProvider>
          <MarketplaceAuthProvider>
            <CartProvider>
              <ClientTracker />
              <Header />
              <main>{children}</main>
              <Footer />
            </CartProvider>
          </MarketplaceAuthProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
