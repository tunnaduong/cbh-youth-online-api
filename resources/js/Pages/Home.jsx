import ForumSection from "@/Components/ForumSection";
import ForumStats from "@/Components/ForumStats";
import TopPosts from "@/Components/TopPosts";
import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";

export default function Home({ mainCategories, latestPosts, stats, currentSort }) {
  return (
    <HomeLayout activeNav="home">
      <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
      <div className="px-2.5">
        <div className="max-w-[775px] mx-auto space-y-6 my-6">
          <TopPosts latestPosts={latestPosts} currentSort={currentSort} />
          <ForumSection mainCategories={mainCategories} />
          <ForumStats stats={stats} />
        </div>
      </div>
    </HomeLayout>
  );
}
