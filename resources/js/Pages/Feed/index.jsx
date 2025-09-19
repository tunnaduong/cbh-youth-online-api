import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import PostItem from "@/Components/PostItem";

export default function Feed({ posts }) {
  console.log(posts);
  return (
    <HomeLayout activeNav="home" activeBar={"feed"}>
      <Head title="Bảng tin" />
      <div className="px-1 xl:min-h-screen pt-4">
        {posts.map((post) => (
          <PostItem key={post.id} post={post} />
        ))}
      </div>
    </HomeLayout>
  );
}
