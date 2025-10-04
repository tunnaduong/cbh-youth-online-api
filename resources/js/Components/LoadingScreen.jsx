import Lottie from "lottie-react";
import splash from "@/Assets/splash.json";

export default function LoadingScreen({ isLoading, children }) {
  if (isLoading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-white dark:!bg-[#2D2F2E] transition-colors duration-300">
        <div className="text-center flex flex-col h-full loading-fade-in">
          {/* Logo or Brand */}
          <div className="flex-1 flex flex-col items-center justify-center">
            <div className="flex items-center justify-center">
              <Lottie animationData={splash} loop={false} style={{ width: 180, height: 180 }} />
            </div>
            <div className="text-xl mt-3 font-light text-[#319527] leading-4 flex flex-col gap-y-2">
              <h1>Diễn đàn học sinh</h1>
              <h1 className="font-bold">Chuyên Biên Hòa</h1>
            </div>
          </div>

          {/* Fatties Logo */}
          <div className="flex items-center justify-center mb-12">
            <img src="/images/fatties.png" alt="Fatties Logo" className="h-16" />
          </div>
        </div>
      </div>
    );
  }

  return <div className={`transition-opacity duration-500`}>{children}</div>;
}
