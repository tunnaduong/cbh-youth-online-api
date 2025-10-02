import { Button } from "antd";

export default function FollowButton({ isFollowing, loading, handleFollow }) {
  return (
    <Button
      onClick={handleFollow}
      loading={loading}
      className={`text-[16px] rounded-full px-3 ${
        isFollowing
          ? "hover:!bg-[#36aa2c] dark:hover:!bg-[#36aa2c] bg-primary-500 border-primary-500 hover:border-primary-500 !text-white"
          : "hover:!bg-primary-500 border-primary-500 hover:border-primary-500 hover:!text-white text-primary-500"
      }`}
    >
      {isFollowing ? "Đang theo dõi" : "Theo dõi"}
    </Button>
  );
}
