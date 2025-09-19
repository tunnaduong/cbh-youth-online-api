import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link } from "@inertiajs/react";
import React, { useState } from "react";
import {
  ArrowDownOutline,
  ArrowUpOutline,
  Bookmark,
  ChatboxOutline,
  EyeOutline,
} from "react-ionicons";
import { ReactPhotoCollage } from "react-photo-collage";
import { usePage } from "@inertiajs/react";
import { CommentInput } from "@/Components/CommentInput";
import Comment from "@/Components/Comment";
import VerifiedBadge from "@/Components/ui/VerifiedBadge";
import { moment } from "@/Utils/momentConfig";
import getCollageSetting from "@/Utils/getCollageSetting";

export default function Show({ post }) {
  const { auth } = usePage().props;
  const [comments, setComments] = useState(post.comments || []);

  console.log(post);

  // Helper function to get time display
  const getTimeDisplay = (comment) => {
    if (comment.created_at) {
      const now = new Date();
      const commentTime = new Date(comment.created_at);
      const diffInMinutes = Math.floor((now - commentTime) / (1000 * 60));

      if (diffInMinutes < 1) return "Just now";
      if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
      if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
      return `${Math.floor(diffInMinutes / 1440)}d ago`;
    }
    return "Unknown time";
  };

  // Handle comment editing
  const handleEditComment = (commentId, newContent) => {
    const updateCommentInTree = (comments) => {
      return comments.map((comment) => {
        if (comment.id === commentId) {
          return { ...comment, content: newContent };
        }
        if (comment.replies) {
          return {
            ...comment,
            replies: updateCommentInTree(comment.replies),
          };
        }
        return comment;
      });
    };

    setComments(updateCommentInTree(comments));
    // Here you would typically make an API call to save the changes
    console.log(`Editing comment ${commentId} with content: ${newContent}`);
  };

  // Handle adding replies
  const handleReplyToComment = (parentId, content) => {
    const newReply = {
      id: Date.now().toString(), // Simple ID generation
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.user.name || auth.user.username,
      },
      created_at: moment(new Date().toISOString()).fromNow(),
      votes: [],
      replies: [],
    };

    const addReplyToComment = (comments, level = 1) => {
      return comments.map((comment) => {
        // Found the target comment
        if (comment.id === parentId) {
          // If this is level 3, add as sibling instead of child
          if (level >= 3) {
            return comment; // Don't add here, will be handled by parent
          }

          // Normal case: add as child
          return {
            ...comment,
            replies: [...(comment.replies || []), newReply],
          };
        }

        // Search in replies
        if (comment.replies && comment.replies.length > 0) {
          // Check if target is in direct children (level 2)
          const directChild = comment.replies.find((reply) => reply.id === parentId);
          if (directChild && level === 1) {
            // Target is at level 2, add normally
            return {
              ...comment,
              replies: comment.replies.map((reply) =>
                reply.id === parentId
                  ? { ...reply, replies: [...(reply.replies || []), newReply] }
                  : reply
              ),
            };
          }

          // Check if target is in grandchildren (level 3)
          const hasLevel3Target = comment.replies.some(
            (reply) => reply.replies && reply.replies.some((r) => r.id === parentId)
          );

          if (hasLevel3Target && level === 1) {
            // Target is at level 3, add as sibling at level 3
            return {
              ...comment,
              replies: comment.replies.map((reply) => {
                if (reply.replies && reply.replies.some((r) => r.id === parentId)) {
                  return {
                    ...reply,
                    replies: [...reply.replies, newReply], // Add as sibling
                  };
                }
                return reply;
              }),
            };
          }

          // Continue searching deeper
          return {
            ...comment,
            replies: addReplyToComment(comment.replies, level + 1),
          };
        }

        return comment;
      });
    };

    const updatedComments = addReplyToComment(comments);
    console.log("Updated comments after reply:", updatedComments);
    setComments(updatedComments);
    // Here you would typically make an API call to save the reply
    console.log(`Replying to comment ${parentId} with content: ${content}`);
  };

  const setting = {
    ...getCollageSetting(post.image_urls),
    photos: post.image_urls.map((url) => ({ source: url })),
    showNumOfRemainingPhotos: true,
  };

  const handleSubmitComment = (content) => {
    const newComment = {
      id: Date.now().toString(), // Simple ID generation
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.user.name || auth.user.username,
      },
      created_at: moment(new Date().toISOString()).fromNow(),
      votes: [],
      replies: [],
    };

    setComments([newComment, ...comments]);
    // TODO: Make an API call to save the comment
  };

  return (
    <HomeLayout activeNav="home" activeBar={null}>
      <Head title={post.title} />
      <div className="px-1 xl:min-h-screen pt-4">
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full">
          <div className="post-container-post post-container mb-4 shadow-lg rounded-xl !p-6 bg-white flex flex-col-reverse md:flex-row">
            <div className="min-w-[84px]">
              <div className="sticky-reaction-bar items-center md:!mt-1 mt-3 gap-x-3 flex md:!flex-col flex-row md:ml-[-20px] text-[13px] font-semibold text-gray-400">
                <ArrowUpOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <span className="select-none text-lg vote-count">
                  {post.votes.reduce((acc, vote) => acc + vote.vote_value, 0)}
                </span>
                <ArrowDownOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <div className="save-post-button bg-[#EAEAEA] dark:bg-neutral-500 cursor-pointer rounded-lg w-[33.6px] h-[33.6px] md:mt-3 flex items-center justify-center">
                  <Bookmark height="20px" width="20px" color={"#9ca3af"} />
                </div>
                <div className="flex-1"></div>
                <div className="flex-1 flex md:hidden flex-row-reverse items-center text-gray-500">
                  <span>{post.view_count}</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>{post.reply_count}+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
            </div>
            <div className="flex-1 overflow-hidden break-words">
              <h1 className="text-xl font-semibold mb-1">{post.title}</h1>
              <div className="text-base max-w-[600px] overflow-wrap prose">
                <p dangerouslySetInnerHTML={{ __html: post.content }} />
              </div>
              {post.image_urls.length != 0 && (
                <div className="square-wrapper mt-3 rounded overflow-hidden">
                  <ReactPhotoCollage {...setting} />
                </div>
              )}
              <hr className="!my-5 border-t-2" />
              <div className="flex-row flex-wrap flex text-[13px] items-center">
                <Link
                  href={route("profile.show", {
                    username: post.author.username,
                  })}
                >
                  <span className="relative flex shrink-0 overflow-hidden rounded-full w-8 h-8">
                    <img
                      className="border rounded-full aspect-square h-full w-full"
                      alt={post.author.username + " avatar"}
                      src={`https://api.chuyenbienhoa.com/v1.0/users/${post.author.username}/avatar`}
                    />
                  </span>
                </Link>
                <span className="text-gray-500 hidden md:block ml-2">Đăng bởi</span>
                <Link
                  className="flex flex-row items-center ml-2 md:ml-1 text-[#319527] hover:text-[#319527] font-bold hover:underline"
                  href={route("profile.show", {
                    username: post.author.username,
                  })}
                >
                  {post.author.profile_name}
                  {post.author.verified && <VerifiedBadge />}
                </Link>
                <span className="mb-2 ml-0.5 text-sm text-gray-500">.</span>
                <span className="ml-0.5 text-gray-500">{post.created_at}</span>
                <div className="flex-1 flex-row-reverse items-center text-gray-500 hidden md:flex">
                  <span>{post.view_count}</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>{post.reply_count}+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full mb-4">
          <div className="shadow !mb-4 long-shadow h-min rounded-lg bg-white post-comment-container overflow-clip">
            <div className="flex flex-col space-y-1.5 p-6 text-xl -mb-4 font-semibold max-w-sm overflow-hidden whitespace-nowrap overflow-ellipsis">
              Bình luận
            </div>
            <div className="p-6 pt-2 pb-0">
              {!auth?.user ? (
                <div className="text-base">
                  <Link className="text-green-600 hover:text-green-600" href="/login">
                    Đăng nhập
                  </Link>{" "}
                  để bình luận và tham gia thảo luận cùng cộng đồng.
                </div>
              ) : (
                <CommentInput onSubmit={handleSubmitComment} />
              )}
              <div className="pb-6 pt-2">
                {comments.map((comment) => (
                  <div key={comment.id} className="mt-6">
                    <Comment
                      comment={comment}
                      level={0}
                      onEdit={handleEditComment}
                      onReply={handleReplyToComment}
                      userAvatar={`https://api.chuyenbienhoa.com/v1.0/users/${auth?.user?.username}/avatar`}
                      getTimeDisplay={getTimeDisplay}
                      parentConnectorHovered={false}
                    />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
