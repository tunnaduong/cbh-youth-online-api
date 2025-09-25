import React, { useState, useEffect } from "react";
import { IoIosAdd } from "react-icons/io";
import { router, usePage } from "@inertiajs/react";
import CreateStoryModal from "./CreateStoryModal";
import { message } from "antd";
import StoryViewerDrawer from "./StoryViewerDrawer";

function StoriesSection() {
  const { auth, stories } = usePage().props;
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [viewerModalOpen, setViewerModalOpen] = useState(false);
  const [selectedUserStories, setSelectedUserStories] = useState(null);
  const [currentStoryIndex, setCurrentStoryIndex] = useState(0);
  const [storiesData, setStoriesData] = useState(stories);
  const [globalStoryIndex, setGlobalStoryIndex] = useState(0);
  const [totalGlobalStories, setTotalGlobalStories] = useState(0);

  // Calculate total global stories
  useEffect(() => {
    const total = storiesData.reduce((sum, userStories) => sum + userStories.stories.length, 0);
    setTotalGlobalStories(total);
  }, [storiesData]);

  // Handle URL routing for stories
  useEffect(() => {
    const handlePopState = () => {
      const urlParams = new URLSearchParams(window.location.search);
      const storyId = urlParams.get("story");

      if (storyId) {
        // Find the story and open it
        const foundStory = storiesData.find((userStories) =>
          userStories.stories.some((story) => story.id == storyId)
        );
        if (foundStory) {
          const storyIndex = foundStory.stories.findIndex((story) => story.id == storyId);
          if (storyIndex !== -1) {
            handleViewStory(foundStory, storyIndex);
          }
        }
      } else {
        // Close modal if not on a story URL
        setViewerModalOpen(false);
      }
    };

    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, [storiesData]);

  // Handle story parameter from URL on component mount
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const storyId = urlParams.get("story");

    if (storyId && storiesData && storiesData.length > 0) {
      // Find the story and open it
      const foundStory = storiesData.find((userStories) =>
        userStories.stories.some((story) => story.id == storyId)
      );
      if (foundStory) {
        const storyIndex = foundStory.stories.findIndex((story) => story.id == storyId);
        if (storyIndex !== -1) {
          handleViewStory(foundStory, storyIndex);
          // Clean up URL parameter
          const url = new URL(window.location);
          url.searchParams.delete("story");
          window.history.replaceState({}, "", url);
        }
      }
    }
  }, [storiesData]);

  const handleCreateStory = () => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để tạo tin");
      router.visit("/login", { preserveScroll: true });
      return;
    }
    setCreateModalOpen(true);
  };

  const handleViewStory = (userStories, storyIndex = 0) => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để xem tin");
      router.visit(
        "/login?continue=" +
          encodeURIComponent(window.location.href + "?story=" + userStories.stories[storyIndex].id),
        {
          preserveScroll: true,
        }
      );
      return;
    }

    // Calculate global story index
    let globalIndex = 0;
    for (let i = 0; i < storiesData.length; i++) {
      if (storiesData[i].id === userStories.id) {
        globalIndex += storyIndex;
        break;
      }
      globalIndex += storiesData[i].stories.length;
    }

    setSelectedUserStories(userStories);
    setCurrentStoryIndex(storyIndex);
    setGlobalStoryIndex(globalIndex);
    setViewerModalOpen(true);

    // Update URL to be shareable
    const storyId = userStories.stories[storyIndex]?.id;
    if (storyId) {
      window.history.pushState(null, "", `/?story=${storyId}`);
    }
  };

  const handleStoriesUpdate = (updatedUserStories, newIndex) => {
    // If no stories left, remove the user completely
    if (updatedUserStories.stories.length === 0) {
      setStoriesData((prevStories) =>
        prevStories.filter((userStories) => userStories.id !== updatedUserStories.id)
      );

      // Close the viewer if this was the selected user
      if (selectedUserStories && selectedUserStories.id === updatedUserStories.id) {
        setViewerModalOpen(false);
        setSelectedUserStories(null);
      }
      return;
    }

    // Update the stories data immediately
    setStoriesData((prevStories) =>
      prevStories.map((userStories) =>
        userStories.id === updatedUserStories.id ? updatedUserStories : userStories
      )
    );

    // Update selected user stories if it's the same user
    if (selectedUserStories && selectedUserStories.id === updatedUserStories.id) {
      setSelectedUserStories(updatedUserStories);
      setCurrentStoryIndex(newIndex);
    }
  };

  const handleStoryCreated = () => {
    // Reload the page to get updated stories
    router.reload({
      only: ["stories"],
      onSuccess: (page) => {
        // Update the local stories data with the new data from server
        setStoriesData(page.props.stories);
      },
    });
  };

  const handleNextUser = () => {
    if (!selectedUserStories || !storiesData) return;

    // Find current user index
    const currentUserIndex = storiesData.findIndex((user) => user.id === selectedUserStories.id);

    // Find next user with stories
    let nextUserIndex = currentUserIndex + 1;
    while (nextUserIndex < storiesData.length && storiesData[nextUserIndex].stories.length === 0) {
      nextUserIndex++;
    }

    if (nextUserIndex < storiesData.length) {
      // Move to next user's first story
      const nextUser = storiesData[nextUserIndex];
      handleViewStory(nextUser, 0);
    } else {
      // No more users with stories, close the drawer
      setViewerModalOpen(false);
      setSelectedUserStories(null);
      window.history.pushState(null, "", "/");
    }
  };

  const handlePreviousUser = () => {
    if (!selectedUserStories || !storiesData) return;

    // Find current user index
    const currentUserIndex = storiesData.findIndex((user) => user.id === selectedUserStories.id);

    // Find previous user with stories
    let prevUserIndex = currentUserIndex - 1;
    while (prevUserIndex >= 0 && storiesData[prevUserIndex].stories.length === 0) {
      prevUserIndex--;
    }

    if (prevUserIndex >= 0) {
      // Move to previous user's last story
      const prevUser = storiesData[prevUserIndex];
      const lastStoryIndex = prevUser.stories.length - 1;
      handleViewStory(prevUser, lastStoryIndex);
    } else {
      // No previous users with stories, stay at current user's first story
      handleViewStory(selectedUserStories, 0);
    }
  };

  const CreateStoryButton = () => (
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
      <div className="bg-white dark:bg-[#3c3c3c] flex flex-col items-center justify-center h-[50px] relative">
        <div className="bg-primary-500 rounded-full border-[4px] border-white dark:border-[#3c3c3c] absolute -top-[20px]">
          <IoIosAdd size={30} color="white" />
        </div>
        <span className="font-semibold text-[13px] text-black dark:text-white mt-3">Tạo tin</span>
      </div>
    </div>
  );

  if (!storiesData || storiesData.length === 0) {
    return (
      <>
        <div className="mx-auto mt-4 flex gap-2 md:scale-100 scale-75 origin-top-left md:w-auto w-[135%] md:overflow-visible overflow-x-auto">
          {/* Create Story Button */}
          <CreateStoryButton />
          <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
          <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
          <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
          <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>
          <div className="w-[115px] h-[195px] bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse xs:block hidden"></div>
        </div>

        {/* Create Story Modal */}
        <CreateStoryModal
          open={createModalOpen}
          onClose={() => setCreateModalOpen(false)}
          onStoryCreated={handleStoryCreated}
        />

        {/* Story Viewer Modal */}
        <StoryViewerDrawer
          open={viewerModalOpen}
          onClose={() => {
            console.log("StoriesSection - onClose called, setting viewerModalOpen to false");
            setViewerModalOpen(false);
            // Reset URL when closing
            window.history.pushState(null, "", "/");
          }}
          userStories={selectedUserStories}
          currentStoryIndex={currentStoryIndex}
          onStoriesUpdate={handleStoriesUpdate}
        />
      </>
    );
  }

  return (
    <div className="mx-auto mt-4 flex gap-2 sm:scale-100 scale-75 origin-top-left">
      {/* Create Story Button */}
      <CreateStoryButton />

      {/* User Stories */}
      {storiesData.map((userStories) => {
        const lastStory = userStories.stories[userStories.stories.length - 1];
        const hasUnviewedStories = userStories.stories.some(
          (story) => !story.viewers?.some((viewer) => viewer.user_id === auth?.user?.id)
        );

        // Skip rendering if no stories
        if (!lastStory) {
          return null;
        }

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
            {lastStory.type === "text" ? (
              <div
                className="w-full flex-1 flex items-center justify-center text-white text-2xl font-bold text-center p-4"
                style={{
                  background: (() => {
                    if (!lastStory.background_color) return "#1877f2";

                    if (
                      lastStory.background_color.startsWith("[") ||
                      lastStory.background_color.startsWith("{")
                    ) {
                      try {
                        const bgColor = JSON.parse(lastStory.background_color);
                        if (Array.isArray(bgColor) && bgColor.length === 2) {
                          return `linear-gradient(135deg, ${bgColor[0]}, ${bgColor[1]})`;
                        }
                      } catch (error) {
                        console.log("Error parsing background_color:", error);
                      }
                    }

                    return lastStory.background_color;
                  })(),
                  fontStyle: lastStory.font_style || "normal",
                }}
              >
                {lastStory.text_content}
              </div>
            ) : lastStory.type === "audio" ? (
              <div className="w-full flex-1 flex flex-col items-center justify-center bg-gray-100 dark:bg-[#3c3c3c]">
                <div className="text-6xl mb-2">🎵</div>
                <div className="text-sm text-gray-600 dark:text-gray-400 text-center px-2">
                  Tin âm thanh
                </div>
              </div>
            ) : (
              <img
                src={
                  lastStory.media_url ||
                  `https://api.chuyenbienhoa.com/v1.0/users/${userStories.username}/avatar`
                }
                className="object-cover w-full flex-1"
                alt="Story"
              />
            )}
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
      <CreateStoryModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onStoryCreated={handleStoryCreated}
      />

      {/* Story Viewer Modal */}
      <StoryViewerDrawer
        open={viewerModalOpen}
        onClose={() => {
          console.log("StoriesSection - onClose called, setting viewerModalOpen to false");
          setViewerModalOpen(false);
          // Reset URL when closing
          window.history.pushState(null, "", "/");
        }}
        userStories={selectedUserStories}
        currentStoryIndex={currentStoryIndex}
        globalStoryIndex={globalStoryIndex}
        totalGlobalStories={totalGlobalStories}
        onStoriesUpdate={handleStoriesUpdate}
        onNextUser={handleNextUser}
        onPreviousUser={handlePreviousUser}
      />
    </div>
  );
}

export default StoriesSection;
