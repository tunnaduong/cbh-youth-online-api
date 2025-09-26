import React, { useState, useEffect, useRef } from "react";
import { IoIosAdd } from "react-icons/io";
import { router, usePage } from "@inertiajs/react";
import CreateStoryModal from "./CreateStoryModal";
import { Drawer, message } from "antd";
import { StoryViewer } from "./StoryViewer";

function StoriesSection() {
  const { auth, stories } = usePage().props;
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [viewerModalOpen, setViewerModalOpen] = useState(false);
  const [selectedUserStories, setSelectedUserStories] = useState(null);
  const [currentStoryIndex, setCurrentStoryIndex] = useState(0);
  const [storiesData, setStoriesData] = useState(stories);
  const isInitialRender = useRef(true);

  useEffect(() => {
    // This effect should only run when the viewer is closed,
    // not on initial render or when it's opened.
    if (isInitialRender.current) {
      isInitialRender.current = false;
      return;
    }

    if (!viewerModalOpen) {
      router.reload({
        only: ["stories"],
        onSuccess: (page) => {
          setStoriesData(page.props.stories);
        },
        preserveState: true,
        showProgress: false,
      });

      // đợi animation Drawer (300ms) rồi reset internal pointers
      setTimeout(() => {
        setSelectedUserStories(null);
        setCurrentStoryIndex(0);
      }, 320);
    }
  }, [viewerModalOpen]);

  const handleCreateStory = () => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để tạo tin");
      router.visit("/login", { preserveScroll: true, showProgress: true });
      return;
    }
    setCreateModalOpen(true);
  };

  const handleViewStory = (userStories, storyIndex = 0) => {
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để xem tin");
      router.visit("/login", { preserveScroll: true });
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
    setViewerModalOpen(true);
  };

  const handleStoryCreated = () => {
    // Reload the page to get updated stories
    router.reload({
      only: ["stories"],
      onSuccess: (page) => {
        // Update the local stories data with the new data from server
        setStoriesData(page.props.stories);
      },
      preserveState: true,
    });
  };

  const CreateStoryButton = () => (
    <div
      className="overflow-hidden rounded-xl shadow-sm w-[90px] h-[160px] sm:w-[115px] sm:h-[195px] flex flex-col cursor-pointer hover:opacity-90 transition-opacity flex-shrink-0"
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
      </>
    );
  }

  return (
    <div className="w-full mx-auto mt-4 flex gap-2 overflow-x-auto flex-nowrap scrollbar-hide">
      {/* Create Story Button */}
      <CreateStoryButton />

      {/* User Stories */}
      {storiesData.map((userStories) => {
        const firstStory = userStories.stories[0];
        const hasUnviewedStories = userStories.stories.some(
          (story) => !story.viewers?.some((viewer) => viewer.user_id === auth?.user?.id)
        );

        // Skip rendering if no stories
        if (!firstStory) {
          return null;
        }

        return (
          <div
            key={userStories.id}
            className="overflow-hidden rounded-xl shadow-sm w-[90px] h-[160px] sm:w-[115px] sm:h-[195px] flex flex-col relative cursor-pointer hover:opacity-90 transition-opacity flex-shrink-0"
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
            {firstStory.type === "text" ? (
              <div
                className="w-full flex-1 flex items-center justify-center text-white text-lg sm:text-2xl font-bold text-center p-2 sm:p-4"
                style={{
                  background: (() => {
                    if (!firstStory.background_color) return "#1877f2";

                    if (
                      firstStory.background_color.startsWith("[") ||
                      firstStory.background_color.startsWith("{")
                    ) {
                      try {
                        const bgColor = JSON.parse(firstStory.background_color);
                        if (Array.isArray(bgColor) && bgColor.length === 2) {
                          return `linear-gradient(135deg, ${bgColor[0]}, ${bgColor[1]})`;
                        }
                      } catch (error) {
                        console.log("Error parsing background_color:", error);
                      }
                    }

                    return firstStory.background_color;
                  })(),
                  fontStyle: firstStory.font_style || "normal",
                }}
              >
                <span className="break-words">{firstStory.text_content}</span>
              </div>
            ) : firstStory.type === "image" ? (
              <img
                src={firstStory.media_url}
                alt={userStories.name}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            ) : (
              <div className="w-full h-full bg-gray-800 flex flex-col items-center justify-center">
                <span className="text-4xl mb-2">{firstStory.type === "audio" ? "🎵" : "🎥"}</span>
                <span className="text-white text-sm">
                  {firstStory.type === "audio" ? "Tin âm thanh" : "Tin video"}
                </span>
              </div>
            )}
            <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/60 to-transparent" />
            <p className="absolute bottom-2 left-1 right-0 text-white text-xs sm:text-sm font-semibold px-1 line-clamp-2 drop-shadow">
              {userStories.name}
            </p>
          </div>
        );
      })}

      {/* Create Story Modal */}
      <CreateStoryModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onStoryCreated={handleStoryCreated}
      />

      <Drawer
        open={viewerModalOpen}
        onClose={() => setViewerModalOpen(false)}
        width="100%"
        height="100%"
        placement="bottom"
        styles={{
          body: { padding: 0, height: "100vh", overflow: "hidden" },
          content: { background: "transparent", opacity: undefined },
          mask: { boxShadow: "none" },
        }}
        mask={false}
        transitionName=""
        destroyOnHidden={false}
        closeIcon={null}
        rootClassName="story-viewer-drawer"
        rootStyle={!viewerModalOpen ? { display: "none" } : {}}
      >
        <StoryViewer
          users={storiesData}
          isOpen={viewerModalOpen}
          onClose={() => setViewerModalOpen(false)}
          selectedUserIndex={
            selectedUserStories
              ? storiesData.findIndex((user) => user.id === selectedUserStories.id)
              : 0
          }
          selectedStoryIndex={currentStoryIndex}
        />
      </Drawer>
    </div>
  );
}

export default StoriesSection;
