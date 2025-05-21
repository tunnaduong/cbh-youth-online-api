import ForumSection from "@/Components/ForumSection";
import TopPosts from "@/Components/TopPosts";
import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import { useEffect } from "react";

export default function Home({
    mainCategories,
    latestPosts,
    stats,
    latestUser,
}) {
    useEffect(() => {
        console.log(mainCategories);
    }, []);

    return (
        <HomeLayout activeNav="home">
            <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
            <>
                <div className="pt-4 !px-2.5">
                    <div className="max-w-[775px] mx-auto">
                        <TopPosts latestPosts={latestPosts} />
                    </div>
                </div>
                <div className="flex flex-1 !p-6 !px-2.5 items-center flex-col -mb-8">
                    {/* Section */}
                    <ForumSection mainCategories={mainCategories} />
                </div>
            </>
        </HomeLayout>
    );
}
