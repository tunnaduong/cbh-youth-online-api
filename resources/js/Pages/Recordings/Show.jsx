import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";

export default function Show({ recording }) {
  return (
    <HomeLayout>
      <Head title="Loa lớn" />
      <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto"></div>
    </HomeLayout>
  );
}
