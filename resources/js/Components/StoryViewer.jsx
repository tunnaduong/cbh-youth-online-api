"use client";
import { useState, useRef, useEffect, useCallback } from "react";
import { useDrag } from "@use-gesture/react";
import { animated, useSpring, config } from "@react-spring/web";
import { Drawer } from "antd";
import { Swiper, SwiperSlide } from "swiper/react";
import { EffectCube } from "swiper/modules";
import { X, ChevronLeft, ChevronRight, VolumeX, Volume2 } from "lucide-react";

// Import Swiper styles
import "swiper/css";
import "swiper/css/effect-cube";
import "swiper/css/navigation";
import "swiper/css/pagination";

const StoryProgress = ({ stories, currentStoryIndex, progress }) => {
  return (
    <div className="absolute top-4 left-4 right-4 z-50 flex gap-1">
      {stories.map((_, index) => (
        <div key={index} className="flex-1 h-1 bg-white/30 rounded-full overflow-hidden">
          <div
            className="h-full bg-white transition-all duration-100 ease-linear"
            style={{
              width:
                index < currentStoryIndex
                  ? "100%"
                  : index === currentStoryIndex
                  ? `${progress}%`
                  : "0%",
            }}
          />
        </div>
      ))}
    </div>
  );
};

const UserHeader = ({ user, onClose, storyType, isMuted, onToggleMute }) => {
  return (
    <div className="absolute top-10 left-4 right-4 z-50 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <img
          src={`https://api.chuyenbienhoa.com/v1.0/users/${user.username}/avatar`}
          alt={user.name}
          className="w-10 h-10 rounded-full border-2 border-white object-cover"
        />
        <span className="text-white font-medium text-sm drop-shadow">{user.name}</span>
      </div>
      <div className="flex items-center gap-2">
        {(storyType === "video" || storyType === "audio") && (
          <button
            onClick={onToggleMute}
            className="text-white hover:text-white/80 transition-colors p-2"
          >
            {isMuted ? (
              <VolumeX size={24} className="drop-shadow" />
            ) : (
              <Volume2 size={24} className="drop-shadow" />
            )}
          </button>
        )}
        <button onClick={onClose} className="text-white hover:text-white/80 transition-colors p-2">
          <X size={24} className="drop-shadow" />
        </button>
      </div>
    </div>
  );
};

const StoryContent = ({ story, isActive, onNext, isMuted }) => {
  const videoRef = useRef(null);
  const audioRef = useRef(null);

  useEffect(() => {
    if (story.type === "video" && videoRef.current) {
      if (isActive) {
        videoRef.current.play();
      } else {
        videoRef.current.pause();
      }
    }
    if (story.type === "audio" && audioRef.current) {
      if (isActive) {
        audioRef.current.play();
      } else {
        audioRef.current.pause();
      }
    }
  }, [isActive, story.type]);

  // Cleanup effect to stop all media when component unmounts
  useEffect(() => {
    return () => {
      if (videoRef.current) {
        videoRef.current.pause();
        videoRef.current.currentTime = 0;
      }
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current.currentTime = 0;
      }
    };
  }, []);

  if (story.type === "video") {
    return (
      <video
        ref={videoRef}
        src={story.media_url}
        className="w-full h-full object-cover"
        autoPlay={isActive}
        muted={isMuted}
        playsInline
        onEnded={onNext}
      />
    );
  }

  if (story.type === "text") {
    return (
      <div
        className="w-full h-full flex items-center justify-center text-white text-2xl font-bold text-center p-4"
        style={{
          background: (() => {
            if (!story.background_color) return "#1877f2";

            if (story.background_color.startsWith("[") || story.background_color.startsWith("{")) {
              try {
                const bgColor = JSON.parse(story.background_color);
                if (Array.isArray(bgColor) && bgColor.length === 2) {
                  return `linear-gradient(135deg, ${bgColor[0]}, ${bgColor[1]})`;
                }
              } catch (error) {
                console.log("Error parsing background_color:", error);
              }
            }

            return story.background_color;
          })(),
          fontStyle: story.font_style || "normal",
        }}
      >
        {story.text_content}
      </div>
    );
  }

  if (story.type === "audio") {
    return (
      <div className="w-full h-full flex flex-col items-center justify-center bg-gray-100 dark:bg-[#3c3c3c]">
        <div className="text-6xl mb-2">🎵</div>
        <div className="text-sm text-gray-600 dark:text-gray-400 text-center px-2">
          Tin âm thanh
        </div>
        <audio
          ref={audioRef}
          src={story.media_url}
          autoPlay={isActive}
          muted={isMuted}
          playsInline
          onEnded={onNext}
          onTimeUpdate={(e) => {
            // This can be used for progress if needed in the future
          }}
        />
      </div>
    );
  }

  return (
    <img
      src={story.media_url || "/placeholder.svg"}
      alt="Story content"
      className="h-full object-contain mx-auto"
    />
  );
};

const StorySlide = ({
  user,
  isActive,
  isViewerOpen,
  onClose,
  onUserChange,
  initialStoryIndex = 0,
}) => {
  const [currentStoryIndex, setCurrentStoryIndex] = useState(initialStoryIndex);
  const [progress, setProgress] = useState(0);
  const [isMuted, setIsMuted] = useState(true);
  const progressIntervalRef = useRef();

  const currentStory = user.stories[currentStoryIndex];

  const handleToggleMute = useCallback(() => {
    setIsMuted((prev) => !prev);
  }, []);

  const stopProgress = useCallback(() => {
    if (progressIntervalRef.current) {
      clearInterval(progressIntervalRef.current);
      progressIntervalRef.current = null;
    }
  }, []);

  const resetProgress = useCallback(() => {
    stopProgress();
    setProgress(0);
  }, [stopProgress]);

  const startProgress = useCallback(() => {
    stopProgress();
    if (currentStory?.type === "image" || currentStory?.type === "text") {
      let duration = currentStory.duration;

      if (!duration || duration <= 0) {
        duration = 5000;
      } else if (duration < 100) {
        // A heuristic to treat small numbers as seconds
        duration *= 1000;
      }

      const interval = 50;
      const increment = (interval / duration) * 100;
      progressIntervalRef.current = setInterval(() => {
        setProgress((prev) => {
          const next = prev + increment;
          return next >= 100 ? 100 : next;
        });
      }, interval);
    }
  }, [currentStory, stopProgress]);

  const handleNextStory = useCallback(() => {
    stopProgress();
    setProgress(0);
    setCurrentStoryIndex((prev) => {
      if (prev < user.stories.length - 1) return prev + 1;
      // last story -> change user
      onUserChange("next");
      return prev;
    });
  }, [user.stories.length, onUserChange, stopProgress]);

  const handlePrevStory = useCallback(() => {
    stopProgress();
    setProgress(0);
    setCurrentStoryIndex((prev) => {
      if (prev > 0) return prev - 1;
      onUserChange("prev");
      return prev;
    });
  }, [onUserChange, stopProgress]);

  useEffect(() => {
    if (progress >= 100 && (currentStory?.type === "image" || currentStory?.type === "text")) {
      handleNextStory();
    }
  }, [progress, currentStory?.type, handleNextStory]);

  useEffect(() => {
    if (isActive && (currentStory?.type === "image" || currentStory?.type === "text")) {
      startProgress();
    } else {
      stopProgress();
    }

    return () => stopProgress();
  }, [isActive, currentStoryIndex, currentStory, startProgress, stopProgress]);

  // RESET khi user thay đổi hoặc khi initialStoryIndex thay đổi (ví dụ khi remount)
  useEffect(() => {
    setCurrentStoryIndex(initialStoryIndex || 0);
    resetProgress();
  }, [user.id, initialStoryIndex, resetProgress]);

  return (
    <div className="relative w-full h-full bg-black">
      <div className="absolute inset-x-0 top-0 h-1/6 bg-gradient-to-b from-black/30 to-transparent pointer-events-none" />

      <StoryProgress
        stories={user.stories}
        currentStoryIndex={currentStoryIndex}
        progress={progress}
      />

      <UserHeader
        user={user}
        onClose={onClose}
        storyType={currentStory?.type}
        isMuted={isMuted}
        onToggleMute={handleToggleMute}
      />

      <StoryContent
        story={currentStory}
        isActive={isActive && isViewerOpen}
        onNext={handleNextStory}
        isMuted={isMuted}
      />

      {/* Touch areas for navigation */}
      <div className="absolute inset-0 flex">
        <div className="flex-1 cursor-pointer" onClick={handlePrevStory} />
        <div className="flex-1 cursor-pointer" onClick={handleNextStory} />
      </div>

      {/* Navigation arrows for desktop */}
      <div className="hidden md:block">
        <button
          onClick={handlePrevStory}
          className="absolute left-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white transition-colors z-40"
        >
          <ChevronLeft size={32} />
        </button>
        <button
          onClick={handleNextStory}
          className="absolute right-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white transition-colors z-40"
        >
          <ChevronRight size={32} />
        </button>
      </div>
    </div>
  );
};

export const StoryViewer = ({
  users,
  isOpen = false,
  onClose,
  onOpacityChange,
  selectedUserIndex = 0,
  selectedStoryIndex = 0,
}) => {
  const [currentUserIndex, setCurrentUserIndex] = useState(selectedUserIndex);
  const swiperRef = useRef(null);
  const [{ y, opacity }, api] = useSpring(() => ({
    y: 0,
    opacity: 1,
    config: config.gentle,
    onChange: ({ value }) => {
      if (onOpacityChange) {
        onOpacityChange(value.opacity);
      }
    },
  }));

  const handleClose = useCallback(() => {
    api.start({
      y: window.innerHeight,
      opacity: 0,
      immediate: false,
      onResolve: onClose,
    });
  }, [api, onClose]);

  // when component mount / isOpen true -> di chuyển swiper đến selectedUserIndex
  useEffect(() => {
    if (isOpen) {
      api.start({ y: 0, opacity: 1, immediate: true });
      setCurrentUserIndex(selectedUserIndex);
      if (swiperRef.current && typeof swiperRef.current.slideTo === "function") {
        swiperRef.current.slideTo(selectedUserIndex, 0); // no animation
      }
    }
  }, [isOpen, selectedUserIndex, api]);

  const handleUserChange = useCallback(
    (direction) => {
      if (direction === "next" && currentUserIndex < users.length - 1) {
        const newIndex = currentUserIndex + 1;
        setCurrentUserIndex(newIndex);
        if (swiperRef.current) {
          swiperRef.current.slideNext();
        }
      } else if (direction === "prev" && currentUserIndex > 0) {
        const newIndex = currentUserIndex - 1;
        setCurrentUserIndex(newIndex);
        if (swiperRef.current) {
          swiperRef.current.slidePrev();
        }
      } else if (direction === "next" && currentUserIndex === users.length - 1) {
        handleClose();
      }
    },
    [currentUserIndex, users.length, handleClose]
  );

  const handleSlideChange = useCallback((swiper) => {
    setCurrentUserIndex(swiper.activeIndex);
  }, []);

  const bind = useDrag(
    ({ last, movement: [, my], velocity: [, vy], direction: [, dy] }) => {
      const isDraggingDown = dy > 0;
      const yPos = my > 0 ? my : 0;

      if (last) {
        if (yPos > window.innerHeight / 3 || (vy > 0.5 && isDraggingDown)) {
          handleClose();
        } else {
          api.start({ y: 0, opacity: 1 }); // Spring back
        }
      } else {
        const newOpacity = 1 - yPos / window.innerHeight;
        api.start({ y: yPos, opacity: newOpacity, immediate: true });
      }
    },
    {
      axis: "y",
      filterTaps: true,
      bounds: { top: 0 },
    }
  );

  return (
    <animated.div
      {...bind()}
      style={{ y, touchAction: "pan-x", opacity }}
      className="w-full h-screen bg-black fixed inset-0"
    >
      <Swiper
        onSwiper={(swiper) => (swiperRef.current = swiper)}
        effect="cube"
        grabCursor={true}
        cubeEffect={{
          shadow: true,
          slideShadows: true,
          shadowOffset: 20,
          shadowScale: 0.94,
        }}
        modules={[EffectCube]}
        className="w-full h-full"
        onSlideChange={handleSlideChange}
        initialSlide={currentUserIndex}
      >
        {users.map((user, index) => (
          <SwiperSlide key={user.id}>
            <StorySlide
              user={user}
              isActive={index === currentUserIndex}
              isViewerOpen={isOpen}
              onClose={handleClose}
              onUserChange={handleUserChange}
              initialStoryIndex={index === selectedUserIndex ? selectedStoryIndex : 0}
            />
          </SwiperSlide>
        ))}
      </Swiper>
    </animated.div>
  );
};
