import React, { useState, useEffect } from "react";
import { IoIosAdd } from "react-icons/io";
import { router, usePage } from "@inertiajs/react";
import CreateStoryModal from "./CreateStoryModal";
import StoryViewerModal from "./StoryViewerModal";
import { message } from "antd";

function StoriesSection() {
  const { auth, stories } = usePage().props;
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [viewerModalOpen, setViewerModalOpen] = useState(false);
  const [selectedUserStories, setSelectedUserStories] = useState(null);
  const [currentStoryIndex, setCurrentStoryIndex] = useState(0);

  const handleCreateStory = () => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để tạo tin");
      router.visit("/login", { preserveScroll: true });
      return;
    }
    setCreateModalOpen(true);
  };

  const handleViewStory = (userStories) => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để xem tin");
      router.visit("/login", { preserveScroll: true });
      return;
    }
    setSelectedUserStories(userStories);
    setCurrentStoryIndex(0);
    setViewerModalOpen(true);
  };

  if (!stories || stories.length === 0) {
    return (
      <div className="mx-auto mt-4 flex gap-2">
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
        <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
      </div>
    );
  }

  return (
    <div className="mx-auto mt-4 flex gap-2">
      {/* Create Story Button */}
      <div
        className="overflow-hidden rounded-xl shadow-sm w-[115px] h-[195px] flex flex-col cursor-pointer hover:opacity-90 transition-opacity"
        onClick={handleCreateStory}
      >
        <img
          src={
            auth?.user
              ? `https://api.chuyenbienhoa.com/v1.0/users/${auth.user.username}/avatar`
              : "/images/story_user.jpg"
          }
          className="object-cover w-full flex-1 h-[145px]"
        />
        <div className="bg-white dark:bg-gray-800 flex flex-col items-center justify-center h-[50px] relative">
          <div className="bg-[#1877f2] rounded-full border-[4px] border-white dark:border-gray-800 absolute -top-[20px]">
            <IoIosAdd size={30} color="white" />
          </div>
          <span className="font-semibold text-[13px] text-black dark:text-white mt-3">Tạo tin</span>
        </div>
      </div>

      {/* User Stories */}
      {stories.map((userStories) => {
        const firstStory = userStories.stories[0];
        const hasUnviewedStories = userStories.stories.some(
          (story) => !story.viewers?.some((viewer) => viewer.user_id === auth?.user?.id)
        );

        return (
          <div
            key={userStories.id}
            className="overflow-hidden rounded-xl shadow-sm w-[115px] h-[195px] flex flex-col relative cursor-pointer hover:opacity-90 transition-opacity"
            onClick={() => handleViewStory(userStories)}
          >
            <div
              className={`absolute top-3 left-2.5 border-[4px] ${
                hasUnviewedStories ? "border-primary-500" : "border-gray-400"
              } rounded-full p-0.5`}
            >
              <img
                src={`https://api.chuyenbienhoa.com/v1.0/users/${userStories.username}/avatar`}
                className="rounded-full w-[25px] h-[25px]"
                alt={userStories.name}
              />
            </div>
            <img
              src={
                firstStory.media_url ||
                `https://api.chuyenbienhoa.com/v1.0/users/${userStories.username}/avatar`
              }
              className="object-cover w-full flex-1"
              alt="Story"
            />
            <div
              className="absolute w-full h-14 bottom-0 px-2.5 flex items-end"
              style={{
                backgroundImage: "linear-gradient(rgba(0, 0, 0, 0) 0%,rgba(0, 0, 0, 0.4) 100%)",
              }}
            >
              <p className="font-semibold text-[13px] text-white text-shadow-md leading-4 mb-2.5">
                {userStories.name}
              </p>
            </div>
          </div>
        );
      })}

      {/* Create Story Modal */}
      <CreateStoryModal open={createModalOpen} onClose={() => setCreateModalOpen(false)} />

      {/* Story Viewer Modal */}
      <StoryViewerModal
        open={viewerModalOpen}
        onClose={() => setViewerModalOpen(false)}
        userStories={selectedUserStories}
        currentStoryIndex={currentStoryIndex}
      />
    </div>
  );
}

export default StoriesSection;
