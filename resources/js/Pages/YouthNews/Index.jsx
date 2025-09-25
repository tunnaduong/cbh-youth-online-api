import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import PostItem from "@/Components/PostItem";

export default function Index({ youthNews }) {
  console.log(youthNews);

  return (
    <HomeLayout activeNav="home" activeBar="news">
      <Head title="Tin tức Đoàn" />

      <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Tin tức Đoàn</h1>

        <div className="space-y-6">
          {youthNews.map((post) => (
            <PostItem key={post.id} post={post} />
          ))}
        </div>
      </div>
    </HomeLayout>
  );
}
