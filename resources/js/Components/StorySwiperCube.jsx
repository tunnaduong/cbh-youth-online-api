import React, { useEffect, useRef } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { EffectCube, Navigation, Pagination, Mousewheel } from "swiper/modules";

// Import Swiper styles
import "swiper/css";
import "swiper/css/effect-cube";
import "swiper/css/navigation";
import "swiper/css/pagination";

const StorySwiperCube = ({
  stories = [],
  currentIndex = 0,
  onIndexChange,
  onNextUser,
  onPreviousUser,
  isFirstGlobalStory = false,
  isLastGlobalStory = false,
}) => {
  const swiperRef = useRef(null);

  const handleNext = () => {
    if (swiperRef.current && swiperRef.current.swiper) {
      swiperRef.current.swiper.slideNext();
    }
  };

  const handlePrevious = () => {
    if (swiperRef.current && swiperRef.current.swiper) {
      swiperRef.current.swiper.slidePrev();
    }
  };

  useEffect(() => {
    if (swiperRef.current && swiperRef.current.swiper) {
      swiperRef.current.swiper.slideTo(currentIndex);
    }
  }, [currentIndex]);

  const handleSlideChange = (swiper) => {
    if (onIndexChange) {
      onIndexChange(swiper.activeIndex);
    }
  };

  const getStoryContent = (story) => {
    if (story.type === "text") {
      return (
        <div
          className="w-full h-full flex items-center justify-center text-white text-4xl font-bold text-center p-8"
          style={{
            background: (() => {
              if (!story.background_color) {
                return "#1877f2";
              }

              // Check if it's a JSON string (starts with [ or {)
              if (
                story.background_color.startsWith("[") ||
                story.background_color.startsWith("{")
              ) {
                try {
                  const bgColor = JSON.parse(story.background_color);
                  if (Array.isArray(bgColor) && bgColor.length === 2) {
                    return `linear-gradient(135deg, ${bgColor[0]}, ${bgColor[1]})`;
                  }
                } catch (error) {
                  console.log("JSON parse error:", error);
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

    if (story.type === "image") {
      return (
        <div className="w-full h-full flex items-center justify-center">
          <img src={story.media_url} alt="Story" className="max-w-full max-h-full object-contain" />
        </div>
      );
    }

    if (story.type === "video") {
      return (
        <div className="w-full h-full flex items-center justify-center">
          <video
            src={story.media_url}
            className="max-w-full max-h-full object-contain"
            autoPlay
            muted
            loop
          />
        </div>
      );
    }

    if (story.type === "audio") {
      return (
        <div className="text-center text-white w-full h-full flex flex-col items-center justify-center">
          <div className="text-6xl mb-4">🎵</div>
          <audio src={story.media_url} controls className="w-full max-w-md mx-auto" autoPlay />
        </div>
      );
    }

    return null;
  };

  if (!stories.length) {
    return (
      <div className="w-full h-full bg-black flex items-center justify-center text-white">
        No stories available
      </div>
    );
  }

  return (
    <div className="w-full h-full bg-black">
      <Swiper
        ref={swiperRef}
        effect="cube"
        grabCursor={true}
        allowTouchMove={true}
        allowSlideNext={true}
        allowSlidePrev={true}
        cubeEffect={{
          shadow: true,
          slideShadows: true,
          shadowOffset: 20,
          shadowScale: 0.94,
        }}
        modules={[EffectCube, Navigation, Pagination, Mousewheel]}
        className="w-full h-full"
        onSlideChange={handleSlideChange}
        onReachBeginning={() => {
          if (onPreviousUser && !isFirstGlobalStory) {
            onPreviousUser();
          }
        }}
        onReachEnd={() => {
          if (onNextUser && !isLastGlobalStory) {
            onNextUser();
          }
        }}
        initialSlide={currentIndex}
      >
        {stories.map((story, index) => (
          <SwiperSlide key={story.id || index}>
            <div className="w-full h-full bg-black">{getStoryContent(story)}</div>
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default StorySwiperCube;
