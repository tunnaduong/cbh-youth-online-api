import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import RecordingItem from "./Partials/RecordingItem";

export default function Index({ recordings }) {
  console.log(recordings);
  return (
    <HomeLayout activeBar={"recordings"}>
      <Head title="Loa lớn" />

      <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6 px-1">Loa lớn</h1>

        {recordings.map((recording) => (
          <RecordingItem recording={recording} />
        ))}
      </div>
    </HomeLayout>
  );
}
