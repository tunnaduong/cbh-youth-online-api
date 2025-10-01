import { useState } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import { Button, ConfigProvider, Input, message } from "antd";
import {
  MessageCircle,
  Edit,
  Check,
  X,
  Share,
  MoreHorizontal,
  ChevronDown,
  ChevronRight,
} from "lucide-react";
import { IoArrowUpSharp, IoArrowDownSharp } from "react-icons/io5";
import { CommentInput } from "./CommentInput";

export default function Comment({
  comment,
  level = 0,
  onEdit,
  onReply,
  userAvatar,
  getTimeDisplay,
  isLast,
  parentConnectorHovered,
}) {
  const { auth } = usePage().props;
  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(comment.content);
  const [isReplying, setIsReplying] = useState(false);
  const [isConnectorHovered, setIsConnectorHovered] = useState(false);
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [localVotes, setLocalVotes] = useState(comment.votes || []);

  const handleSaveEdit = () => {
    if (comment.isPending) return;
    if (onEdit) {
      onEdit(comment.id, editContent);
    }
    setIsEditing(false);
  };

  const handleCancelEdit = () => {
    setEditContent(comment.content);
    setIsEditing(false);
  };

  const handleSubmitReply = (content) => {
    if (comment.isPending) return;
    if (onReply) {
      onReply(comment.id, content);
    }
    setIsReplying(false);
  };

  const handleCancelReply = () => {
    setIsReplying(false);
  };

  const handleVote = async (voteValue) => {
    if (comment.isPending) return;
    if (!auth.user) {
      message.error("Bạn cần đăng nhập để thực hiện hành động này");
      router.visit(route("login") + "?continue=" + encodeURIComponent(window.location.href));
      return;
    }

    const username = auth.user.username;
    const existingVote = localVotes.find((vote) => vote.username === username);

    // Optimistically update the UI
    let newVotes = [...localVotes];

    if (existingVote) {
      if (existingVote.vote_value === voteValue) {
        // User is undoing their vote
        newVotes = localVotes.filter((vote) => vote.username !== username);
      } else {
        // User is changing their vote
        newVotes = localVotes.map((vote) =>
          vote.username === username ? { ...vote, vote_value: voteValue } : vote
        );
      }
    } else {
      // User is voting for the first time
      newVotes = [...localVotes, { username, vote_value: voteValue }];
    }

    setLocalVotes(newVotes);

    // Send the vote to the backend
    try {
      router.post(
        route("comments.vote", { comment: comment.id }),
        { vote_value: voteValue },
        {
          preserveScroll: true,
          preserveState: true,
          onError: (errors) => {
            // Revert optimistic update on error
            setLocalVotes(localVotes);
            message.error("Có lỗi xảy ra khi vote bình luận");
            console.error("Error voting on comment:", errors);
          },
        }
      );
    } catch (error) {
      // Revert optimistic update on error
      setLocalVotes(localVotes);
      console.error("Error voting on comment:", error);
    }
  };

  const voteCount = localVotes ? localVotes.reduce((acc, vote) => acc + vote.vote_value, 0) : 0;

  const userVote = auth.user
    ? localVotes.find((vote) => vote.username === auth.user.username)
    : null;

  const userVoteValue = userVote ? userVote.vote_value : 0;

  const CollapseIcon = () => <ChevronDown className="w-4 h-4" />;

  const ExpandIcon = () => <ChevronRight className="w-4 h-4" />;

  const UpvoteIcon = () => <IoArrowUpSharp size={16} />;
  const DownvoteIcon = () => <IoArrowDownSharp size={16} />;

  return (
    <div className="relative">
      {/* Connector lines for nested comments */}
      {comment.replies?.length > 0 && !isCollapsed && (
        <div
          className={`absolute ${
            isConnectorHovered ? "bg-black  dark:!bg-white" : "bg-gray-200 dark:!bg-gray-600"
          }`}
          style={{
            left: "20px",
            top: "40px",
            height: "100%",
            width: "1px",
          }}
          onMouseEnter={() => setIsConnectorHovered(true)}
          onMouseLeave={() => setIsConnectorHovered(false)}
        />
      )}

      {level > 0 && (
        <div
          className={`absolute w-3 ${
            parentConnectorHovered ? "bg-black dark:!bg-white" : "bg-gray-200 dark:!bg-gray-600"
          }`}
          style={{ left: "-12px", top: "20px", height: "1px" }}
        />
      )}

      {isLast && (
        <div
          className="absolute bg-background"
          style={{ left: "-12px", top: "21px", height: "100%", width: "1px" }}
        />
      )}

      {/* Comment content */}
      <div className={"mb-4"}>
        <div className="flex gap-3">
          {/* Avatar or Collapse Button */}
          <div className="flex-shrink-0">
            {isCollapsed ? (
              <div
                className="w-10 h-10 rounded-full bg-white dark:!bg-[#3C3C3C] flex items-center justify-center cursor-pointer border border-gray-200 dark:!border-gray-600"
                onClick={() => setIsCollapsed(!isCollapsed)}
              >
                <ExpandIcon />
              </div>
            ) : (
              <Link href={route("profile.show", { username: comment.author.username })}>
                <img
                  src={`https://api.chuyenbienhoa.com/v1.0/users/${comment.author.username}/avatar`}
                  alt={`${comment.author.profile_name}'s avatar`}
                  className="w-10 h-10 rounded-full object-cover border border-gray-200"
                />
              </Link>
            )}
          </div>

          {/* Comment text or edit form */}
          <div className="flex-1 min-w-0" style={{ paddingTop: "8px" }}>
            {/* Header */}
            <div className="flex items-center gap-2 mb-2">
              <Link href={route("profile.show", { username: comment.author.username })}>
                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">
                  {comment.author.profile_name}
                </span>
              </Link>
              <span className="text-gray-400">•</span>
              <span className="text-gray-500 dark:!text-gray-400 text-sm">
                {comment.created_at}
              </span>
            </div>

            {/* Comment text or edit form */}
            {isEditing ? (
              <div className="space-y-2 mb-3">
                <Input.TextArea
                  value={editContent}
                  onChange={(e) => setEditContent(e.target.value)}
                  className="w-full min-h-[60px] p-2 text-sm"
                  placeholder="Sửa bình luận của bạn..."
                />
                <div className="flex gap-2">
                  <Button
                    size="small"
                    type="primary"
                    onClick={handleSaveEdit}
                    className="flex items-center gap-1"
                  >
                    <Check className="w-3 h-3" />
                    Lưu
                  </Button>
                  <Button
                    size="small"
                    onClick={handleCancelEdit}
                    className="flex items-center gap-1"
                  >
                    <X className="w-3 h-3" />
                    Hủy
                  </Button>
                </div>
              </div>
            ) : (
              !isCollapsed && (
                <div className="text-gray-700 dark:text-gray-300 text-sm mb-1 whitespace-pre-wrap">
                  {comment.content}
                </div>
              )
            )}

            {/* Actions */}
            {!isCollapsed && !comment.isPending && (
              <div className="flex items-center gap-1 relative" style={{ marginLeft: "-10px" }}>
                {comment.replies?.length > 0 && (
                  <div
                    className="absolute cursor-pointer z-10"
                    style={{ left: "-31px", top: "8px" }}
                    onClick={() => setIsCollapsed(!isCollapsed)}
                  >
                    <div className="bg-white dark:!bg-[#3C3C3C] rounded-full border border-gray-200 dark:!border-gray-600">
                      <CollapseIcon />
                    </div>
                  </div>
                )}

                {/* Vote buttons */}
                <Button
                  size="small"
                  className={`h-8 px-2 rounded-full border-0 ${
                    userVoteValue === 1
                      ? "text-primary-500"
                      : "text-gray-500 hover:!text-primary-500"
                  }`}
                  onClick={() => handleVote(1)}
                >
                  <UpvoteIcon />
                </Button>
                <span
                  className={`text-xs font-medium min-w-[1rem] text-center ${
                    userVoteValue === 1
                      ? "text-primary-500"
                      : userVoteValue === -1
                      ? "text-red-600"
                      : "text-gray-500 dark:!text-gray-400"
                  }`}
                >
                  {voteCount}
                </span>
                <ConfigProvider
                  theme={{
                    components: {
                      Button: {
                        colorPrimaryHover: "#df0909",
                        colorPrimaryActive: "#df0909",
                      },
                    },
                  }}
                >
                  <Button
                    size="small"
                    className={`h-8 px-2 text-gray-500 hover:!text-red-500 hover:!bg-red-50 dark:hover:!bg-[rgba(69,10,10,0.2)] rounded-full
                    border-0 ${
                      userVoteValue === -1
                        ? "text-red-600 dark:!text-red-400"
                        : "text-gray-500 hover:!text-red-500"
                    }`}
                    onClick={() => handleVote(-1)}
                  >
                    <DownvoteIcon />
                  </Button>
                </ConfigProvider>

                {/* Action buttons */}
                <Button
                  size="small"
                  className="h-8 px-2 text-xs text-gray-500 dark:!text-gray-400 hover:text-gray-700 border-0 rounded-full"
                  onClick={() => {
                    if (!auth.user) {
                      message.error("Bạn cần đăng nhập để thực hiện hành động này");
                      router.visit(
                        route("login") + "?continue=" + encodeURIComponent(window.location.href)
                      );
                    } else {
                      setIsReplying(!isReplying);
                    }
                  }}
                >
                  <MessageCircle className="w-4 h-4" />
                  <span className="hidden sm:inline">Trả lời</span>
                </Button>
                {auth.user && auth.user.id === comment.author.id && (
                  <Button
                    size="small"
                    className="h-8 px-2 text-xs text-gray-500 dark:!text-gray-400 hover:text-gray-700 border-0 rounded-full"
                    onClick={() => setIsEditing(!isEditing)}
                  >
                    <Edit className="w-4 h-4" />
                  </Button>
                )}
                <Button
                  size="small"
                  className="h-8 px-2 text-xs text-gray-500 dark:!text-gray-400 hover:text-gray-700 border-0 rounded-full"
                >
                  <MoreHorizontal className="w-4 h-4" />
                </Button>
              </div>
            )}

            {/* Reply Input */}
            {isReplying && !isCollapsed && !comment.isPending && (
              <div className="mt-4">
                <CommentInput
                  placeholder="Nhập trả lời của bạn..."
                  onSubmit={handleSubmitReply}
                  userAvatar={userAvatar}
                  onCancel={handleCancelReply}
                />
              </div>
            )}

            {/* Pending state helper text */}
            {comment.isPending && !isCollapsed && (
              <div className="text-sm text-gray-400 mt-2">Đang viết...</div>
            )}
          </div>
        </div>
      </div>

      {/* Nested Replies */}
      {comment.replies?.length > 0 && !isCollapsed && (
        <div className="ml-8 space-y-0">
          {comment.replies.map((reply, index) => (
            <Comment
              key={reply.id}
              comment={reply}
              isReply={true}
              level={level + 1}
              onEdit={onEdit}
              onReply={onReply}
              userAvatar={userAvatar}
              getTimeDisplay={getTimeDisplay}
              isLast={index === comment.replies.length - 1}
              parentConnectorHovered={isConnectorHovered}
            />
          ))}
        </div>
      )}
    </div>
  );
}
