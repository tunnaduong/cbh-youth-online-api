import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import React from "react";

export default function Subforum() {
  return (
    <HomeLayout activeNav="home">
      <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
      <div className="px-2.5 min-h-screen">Test</div>
    </HomeLayout>
  );
}
