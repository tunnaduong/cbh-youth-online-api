import React, { useState, useEffect } from "react";
import { Modal, Button, message } from "antd";
import { LeftOutlined, RightOutlined, HeartOutlined, DeleteOutlined } from "@ant-design/icons";
import { usePage, router } from "@inertiajs/react";

const StoryViewerModal = ({ open, onClose, userStories, currentStoryIndex = 0 }) => {
  const { auth } = usePage().props;
  const [currentIndex, setCurrentIndex] = useState(currentStoryIndex);
  const [progress, setProgress] = useState(0);
  const [isPlaying, setIsPlaying] = useState(true);
  const [viewedStories, setViewedStories] = useState(new Set());

  const currentStory = userStories?.stories?.[currentIndex];
  const totalStories = userStories?.stories?.length || 0;

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
      }
    );
  };

  const handleNext = () => {
    if (currentIndex < totalStories - 1) {
      setCurrentIndex((prev) => prev + 1);
    } else {
      onClose();
    }
  };

  const handlePrevious = () => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
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
      }
    );
  };

  const handleDelete = () => {
    if (!currentStory || currentStory.user_id !== auth?.user?.id) return;

    router.delete(route("stories.destroy", currentStory.id), {
      onSuccess: () => {
        message.success("Đã xóa tin");
        handleNext();
      },
      onError: (error) => {
        console.error("Error deleting story:", error);
      },
    });
  };

  const handlePlayPause = () => {
    setIsPlaying(!isPlaying);
  };

  if (!open || !userStories || !currentStory) {
    return null;
  }

  return (
    <Modal
      open={open}
      onCancel={onClose}
      footer={null}
      width="100%"
      style={{
        top: 0,
        paddingBottom: 0,
        maxWidth: "100vw",
        height: "100vh",
      }}
      className="story-viewer-modal"
    >
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
                    index < currentIndex ? "100%" : index === currentIndex ? `${progress}%` : "0%",
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
          disabled={currentIndex === 0}
        />

        <Button
          icon={<RightOutlined />}
          className="absolute right-4 top-1/2 transform -translate-y-1/2 z-10 bg-black bg-opacity-50 border-none text-white hover:bg-opacity-70"
          onClick={handleNext}
          disabled={currentIndex === totalStories - 1}
        />

        {/* Story Content */}
        <div className="relative w-full h-full flex items-center justify-center">
          {currentStory.type === "text" ? (
            <div
              className="w-full h-full flex items-center justify-center text-white text-4xl font-bold text-center p-8"
              style={{
                background: Array.isArray(currentStory.background_color)
                  ? `linear-gradient(135deg, ${currentStory.background_color[0]}, ${currentStory.background_color[1]})`
                  : currentStory.background_color || "#1877f2",
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
                <div className="text-center text-white">
                  <div className="text-6xl mb-4">🎵</div>
                  <audio src={currentStory.media_url} controls className="w-full max-w-md" />
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
      </div>
    </Modal>
  );
};

export default StoryViewerModal;
