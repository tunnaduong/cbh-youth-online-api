import { useState } from "react";
import { Link } from "@inertiajs/react";
import { Button } from "antd";
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
  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(comment.content);
  const [isReplying, setIsReplying] = useState(false);
  const [isConnectorHovered, setIsConnectorHovered] = useState(false);
  const [isCollapsed, setIsCollapsed] = useState(false);

  const handleSaveEdit = () => {
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
    if (onReply) {
      onReply(comment.id, content);
    }
    setIsReplying(false);
  };

  const handleCancelReply = () => {
    setIsReplying(false);
  };

  const voteCount = comment.votes
    ? comment.votes.reduce((acc, vote) => acc + vote.vote_value, 0)
    : 0;

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
          style={{ left: "-12px", top: "21px", height: "100vh", width: "1px" }}
        />
      )}

      {/* Comment content */}
      <div className={isLast ? "mb-0" : "mb-4"}>
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
              <Link href={`/users/${comment.author.username}`}>
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
              <Link href={`/users/${comment.author.username}`}>
                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">
                  {comment.author.profile_name}
                </span>
              </Link>
              <span className="text-gray-400">•</span>
              <span className="text-gray-500 text-sm">{comment.created_at}</span>
            </div>

            {/* Comment text or edit form */}
            {isEditing ? (
              <div className="space-y-2 mb-3">
                <textarea
                  value={editContent}
                  onChange={(e) => setEditContent(e.target.value)}
                  className="w-full min-h-[60px] p-2 text-sm border border-gray-300 dark:!border-gray-600 dark:!bg-[#3C3C3C] rounded-md resize-y focus:outline-none focus:ring-2 focus:ring-blue-500"
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
            {!isCollapsed && (
              <div className="flex items-center gap-1 relative" style={{ marginLeft: "-10px" }}>
                {comment.replies?.length > 0 && (
                  <div
                    className="absolute cursor-pointer z-10"
                    style={{ left: "-34px", top: "8px" }}
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
                  className="h-8 px-2 text-gray-500 hover:text-orange-600 hover:bg-orange-50 rounded-full border-0"
                >
                  <UpvoteIcon />
                </Button>
                <span className="text-xs font-medium text-gray-500 min-w-[1rem] text-center">
                  {voteCount}
                </span>
                <Button
                  size="small"
                  className="h-8 px-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-full border-0"
                >
                  <DownvoteIcon />
                </Button>

                {/* Action buttons */}
                <Button
                  size="small"
                  className="h-8 px-2 text-xs text-gray-500 hover:text-gray-700 border-0 rounded-full"
                  onClick={() => setIsReplying(!isReplying)}
                >
                  <MessageCircle className="w-4 h-4" />
                  <span className="hidden sm:inline">Trả lời</span>
                </Button>
                <Button
                  size="small"
                  className="h-8 px-2 text-xs text-gray-500 hover:text-gray-700 border-0 rounded-full"
                  onClick={() => setIsEditing(!isEditing)}
                >
                  <Edit className="w-4 h-4" />
                </Button>
                <Button
                  size="small"
                  className="h-8 px-2 text-xs text-gray-500 hover:text-gray-700 border-0 rounded-full"
                >
                  <MoreHorizontal className="w-4 h-4" />
                </Button>
              </div>
            )}

            {/* Reply Input */}
            {isReplying && !isCollapsed && (
              <div className="mt-4">
                <CommentInput
                  placeholder="Nhập trả lời của bạn..."
                  onSubmit={handleSubmitReply}
                  userAvatar={userAvatar}
                  onCancel={handleCancelReply}
                />
              </div>
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
