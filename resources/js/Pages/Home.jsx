import ForumSection from "@/Components/ForumSection";
import ForumStats from "@/Components/ForumStats";
import SEOContent from "@/Components/SEOContent";
import StoriesSection from "@/Components/StoriesSection";
import TopPosts from "@/Components/TopPosts";
import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";

export default function Home({ mainCategories, latestPosts, stats, currentSort }) {
  return (
    <HomeLayout activeNav="home">
      <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
      <div className="px-2.5">
        <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto space-y-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Diễn đàn</h1>
          <StoriesSection />
          <TopPosts latestPosts={latestPosts} currentSort={currentSort} />
          <ForumSection mainCategories={mainCategories} />
          <ForumStats stats={stats} />
          <SEOContent />
        </div>
      </div>
    </HomeLayout>
  );
}
