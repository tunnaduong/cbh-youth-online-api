import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

export default function HelpCenterLayout({ children, title, auth }) {
  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title={title} />
      <div className="py-6">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-[#3c3c3c] overflow-hidden shadow-md sm:rounded-xl">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <div className="flex flex-col md:flex-row -mx-4">{children}</div>
              <div className="px-3">
                <div className="mx-auto pt-6">
                  <hr className="mb-4" />
                  <div className="flex items-center justify-between">
                    <a href="https://fatties.vercel.app" target="_blank">
                      <img
                        src="/images/from_fatties.png"
                        alt="Fatties Logo"
                        className="h-6 w-auto -ml-0.5"
                      />
                    </a>
                    <div className="ml-4 text-[12px] text-gray-500">
                      © {new Date().getFullYear()} Fatties Software
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
