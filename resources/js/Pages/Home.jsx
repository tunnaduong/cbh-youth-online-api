import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";

export default function Home({
    mainCategories,
    latestPosts,
    stats,
    latestUser,
}) {
    return (
        <HomeLayout>
            <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
            <h1>Home</h1>
            <div className="">
                <button className="btn btn-primary">Test Button</button>
            </div>
        </HomeLayout>
    );
}
