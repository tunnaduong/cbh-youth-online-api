import React, { useState, useEffect } from "react";
import { Button, message, Drawer } from "antd";
import { LeftOutlined, RightOutlined, HeartOutlined, DeleteOutlined } from "@ant-design/icons";
import { usePage, router } from "@inertiajs/react";

const StoryViewerDrawer = ({
  open,
  onClose,
  userStories = [],
  currentStoryIndex = 0,
  globalStoryIndex = 0,
  totalGlobalStories = 0,
  onStoriesUpdate,
  onNextUser,
  onPreviousUser,
}) => {
  const { auth } = usePage().props;
  const [currentIndex, setCurrentIndex] = useState(currentStoryIndex);
  const [progress, setProgress] = useState(0);
  const [isPlaying, setIsPlaying] = useState(true);
  const [viewedStories, setViewedStories] = useState(new Set());
  const [isAnimating, setIsAnimating] = useState(false);
  const [animationDirection, setAnimationDirection] = useState("next");
  // Always use cube effect

  // Debug: Log when open prop changes
  useEffect(() => {
    console.log("StoryViewerModal - open prop changed:", open);
  }, [open]);

  const currentStory = userStories?.stories?.[currentIndex];
  const totalStories = userStories?.stories?.length || 0;

  // Calculate disabled states based on global position
  const isFirstGlobalStory = globalStoryIndex === 0;
  const isLastGlobalStory = globalStoryIndex === totalGlobalStories - 1;

  useEffect(() => {
    if (open && currentStory) {
      setProgress(0);
      setIsPlaying(true);
      markAsViewed(currentStory.id);
    }
  }, [open, currentStory]);

  useEffect(() => {
    if (!isPlaying || !currentStory) return;

    const duration = currentStory.duration || 5;
    const interval = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          handleNext();
          return 0;
        }
        return prev + 100 / (duration * 10); // Update every 100ms
      });
    }, 100);

    return () => clearInterval(interval);
  }, [isPlaying, currentStory]);

  const markAsViewed = (storyId) => {
    if (viewedStories.has(storyId)) return;

    router.post(
      route("stories.view", storyId),
      {},
      {
        onSuccess: () => {
          setViewedStories((prev) => new Set([...prev, storyId]));
        },
        onError: (error) => {
          console.error("Error marking story as viewed:", error);
        },
        preserveState: true,
        preserveScroll: true,
        only: [], // Don't update any props to avoid NProgress
      }
    );
  };

  const handleNext = () => {
    if (isAnimating) return;

    if (currentIndex < totalStories - 1) {
      setIsAnimating(true);
      setAnimationDirection("next");
      const newIndex = currentIndex + 1;

      setTimeout(() => {
        setCurrentIndex(newIndex);
        setProgress(0);
        setIsAnimating(false);
        // Update URL
        const storyId = userStories.stories[newIndex]?.id;
        if (storyId) {
          window.history.pushState(null, "", `/?story=${storyId}`);
        }
      }, 500);
    } else {
      console.log("StoryViewerModal - handleNext calling onNextUser");
      if (onNextUser) {
        // Reset progress bar when switching to next user
        setProgress(0);
        setIsPlaying(true);
        onNextUser();
      } else {
        onClose();
      }
    }
  };

  const handlePrevious = () => {
    if (isAnimating) return;

    if (currentIndex > 0) {
      setIsAnimating(true);
      setAnimationDirection("prev");
      const newIndex = currentIndex - 1;

      setTimeout(() => {
        setCurrentIndex(newIndex);
        setProgress(0);
        setIsAnimating(false);
        // Update URL
        const storyId = userStories.stories[newIndex]?.id;
        if (storyId) {
          window.history.pushState(null, "", `/?story=${storyId}`);
        }
      }, 500);
    } else {
      // At first story, move to previous user's last story
      if (onPreviousUser) {
        // Reset progress bar when switching to previous user
        setProgress(0);
        setIsPlaying(true);
        onPreviousUser();
      }
    }
  };

  const handleReact = () => {
    if (!currentStory) return;

    router.post(
      route("stories.react", currentStory.id),
      {
        reaction_type: "like",
      },
      {
        onSuccess: () => {
          message.success("Đã thích tin này!");
        },
        onError: (error) => {
          console.error("Error reacting to story:", error);
        },
        preserveState: true,
        preserveScroll: true,
        only: [],
      }
    );
  };

  const handleDelete = () => {
    if (!currentStory || currentStory.user_id !== auth?.user?.id) return;

    // Remove the story from the current userStories immediately
    const updatedStories = userStories.stories.filter((story) => story.id !== currentStory.id);
    const updatedUserStories = {
      ...userStories,
      stories: updatedStories,
    };

    // Update the current index if needed
    let newIndex = currentIndex;
    if (currentIndex >= updatedStories.length) {
      newIndex = updatedStories.length - 1;
    }

    // Update the parent component with the new data
    if (typeof onStoriesUpdate === "function") {
      onStoriesUpdate(updatedUserStories, newIndex);
    }

    // Update URL immediately if there are still stories
    if (updatedStories.length > 0 && newIndex < updatedStories.length) {
      const storyId = updatedStories[newIndex]?.id;
      if (storyId) {
        window.history.pushState(null, "", `/?story=${storyId}`);
      }
    }

    router.delete(route("stories.destroy", currentStory.id), {
      onSuccess: () => {
        message.success("Đã xóa tin");
        // Navigate to next story or move to next user if no more stories
        if (updatedStories.length === 0) {
          if (onNextUser) {
            onNextUser();
          } else {
            onClose();
          }
        } else if (newIndex < updatedStories.length) {
          setCurrentIndex(newIndex);
        } else {
          if (onNextUser) {
            onNextUser();
          } else {
            onClose();
          }
        }
      },
      onError: (error) => {
        console.error("Error deleting story:", error);
        // Revert the optimistic update on error
        if (typeof onStoriesUpdate === "function") {
          onStoriesUpdate(userStories, currentIndex);
        }
      },
      preserveState: true,
      preserveScroll: true,
      only: [],
    });
  };

  const handlePlayPause = () => {
    setIsPlaying(!isPlaying);
  };

  const handleCubeIndexChange = (newIndex) => {
    setCurrentIndex(newIndex);
    setProgress(0);
    setIsPlaying(true);

    // Update URL
    const storyId = userStories.stories[newIndex]?.id;
    if (storyId) {
      window.history.pushState(null, "", `/?story=${storyId}`);
    }
  };
  return (
    <Drawer
      open={open}
      height="100vh"
      styles={{
        body: {
          padding: 0,
        },
        header: {
          display: "none",
        },
        footer: {
          display: "none",
        },
      }}
      placement={"bottom"}
      className="story-viewer-modal"
    >
      {userStories && currentStory ? (
        <div className="relative w-full h-screen bg-black flex items-center justify-center">
          {/* Progress Bars */}
          <div className="absolute top-4 left-4 right-4 flex gap-1 z-10">
            {userStories.stories.map((_, index) => (
              <div
                key={index}
                className="flex-1 h-1 bg-white bg-opacity-30 rounded-full overflow-hidden"
              >
                <div
                  className="h-full bg-white transition-all duration-100"
                  style={{
                    width:
                      index < currentIndex
                        ? "100%"
                        : index === currentIndex
                        ? `${progress}%`
                        : "0%",
                  }}
                />
              </div>
            ))}
          </div>

          {/* Navigation Buttons */}
          <Button
            icon={<LeftOutlined />}
            className="absolute left-4 top-1/2 transform -translate-y-1/2 z-10 bg-black bg-opacity-50 border-none text-white hover:bg-opacity-70"
            onClick={handlePrevious}
            disabled={isFirstGlobalStory}
          />

          <Button
            icon={<RightOutlined />}
            className="absolute right-4 top-1/2 transform -translate-y-1/2 z-10 bg-black bg-opacity-50 border-none text-white hover:bg-opacity-70"
            onClick={handleNext}
            disabled={isLastGlobalStory}
          />

          {/* Story Content */}
          <div className="relative w-full h-full flex items-center justify-center">
            {currentStory.type === "text" ? (
              <div
                className="w-full h-full flex items-center justify-center text-white text-4xl font-bold text-center p-8"
                style={{
                  background: (() => {
                    console.log(
                      "StoryViewerModal - currentStory.background_color:",
                      currentStory.background_color
                    );

                    if (!currentStory.background_color) {
                      console.log("StoryViewerModal - no background_color, using default");
                      return "#1877f2";
                    }

                    // Check if it's a JSON string (starts with [ or {)
                    if (
                      currentStory.background_color.startsWith("[") ||
                      currentStory.background_color.startsWith("{")
                    ) {
                      try {
                        const bgColor = JSON.parse(currentStory.background_color);
                        console.log("StoryViewerModal - parsed bgColor:", bgColor);

                        if (Array.isArray(bgColor) && bgColor.length === 2) {
                          const gradient = `linear-gradient(135deg, ${bgColor[0]}, ${bgColor[1]})`;
                          console.log("StoryViewerModal - using gradient:", gradient);
                          return gradient;
                        }
                      } catch (error) {
                        console.log("StoryViewerModal - JSON parse error:", error);
                      }
                    }

                    // If it's a single color string or JSON parse failed, use it directly
                    console.log(
                      "StoryViewerModal - using single color:",
                      currentStory.background_color
                    );
                    return currentStory.background_color;
                  })(),
                  fontStyle: currentStory.font_style || "normal",
                }}
              >
                {currentStory.text_content}
              </div>
            ) : (
              <div className="w-full h-full flex items-center justify-center">
                {currentStory.type === "image" && (
                  <img
                    src={currentStory.media_url}
                    alt="Story"
                    className="max-w-full max-h-full object-contain"
                  />
                )}
                {currentStory.type === "video" && (
                  <video
                    src={currentStory.media_url}
                    className="max-w-full max-h-full object-contain"
                    autoPlay
                    muted
                    loop
                  />
                )}
                {currentStory.type === "audio" && (
                  <div className="text-center text-white w-full">
                    <div className="text-6xl mb-4">🎵</div>
                    <audio
                      src={currentStory.media_url}
                      controls
                      className="w-full max-w-md mx-auto"
                      autoPlay
                    />
                  </div>
                )}
              </div>
            )}
          </div>

          {/* User Info */}
          <div className="absolute bottom-4 left-4 right-4 flex items-center justify-between z-10">
            <div className="flex items-center space-x-3">
              <img
                src={`https://api.chuyenbienhoa.com/v1.0/users/${userStories.username}/avatar`}
                alt={userStories.name}
                className="w-10 h-10 rounded-full border-2 border-white"
              />
              <div>
                <p className="text-white font-semibold">{userStories.name}</p>
                <p className="text-white text-sm opacity-75">
                  {new Date(currentStory.created_at).toLocaleTimeString("vi-VN", {
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <Button
                icon={<HeartOutlined />}
                className="bg-black bg-opacity-50 border-none text-white hover:bg-opacity-70"
                onClick={handleReact}
              />

              {currentStory.user_id === auth?.user?.id && (
                <Button
                  icon={<DeleteOutlined />}
                  className="bg-black bg-opacity-50 border-none text-red-400 hover:bg-opacity-70"
                  onClick={handleDelete}
                />
              )}
            </div>
          </div>

          {/* Play/Pause Overlay */}
          <div className="absolute inset-0 z-5" onClick={handlePlayPause} />

          {/* Close Button */}
          <Button
            className="absolute top-4 right-4 z-20 bg-black bg-opacity-50 border-none text-white hover:bg-opacity-70"
            onClick={onClose}
          >
            ✕
          </Button>
        </div>
      ) : null}
    </Drawer>
  );
};

export default StoryViewerDrawer;
