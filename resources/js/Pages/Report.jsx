import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"; // Assuming you have a layout

export default function ReportPage({ auth }) {
  // 'auth' prop is common in Inertia apps
  return (
    <AuthenticatedLayout
      user={auth.user} // Pass user to layout
      header={
        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Báo cáo <ion-icon name="alert-circle-outline"></ion-icon>
        </h2>
      }
    >
      <Head title="Báo cáo" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <p>Nội dung trang báo cáo ở đây.</p>
              <p className="mt-4">
                Here are some Ionicons:
                <ion-icon
                  name="heart"
                  style={{
                    color: "red",
                    fontSize: "24px",
                    marginLeft: "8px",
                  }}
                ></ion-icon>
                <ion-icon
                  name="star"
                  style={{
                    color: "gold",
                    fontSize: "24px",
                    marginLeft: "8px",
                  }}
                ></ion-icon>
                <ion-icon
                  name="thumbs-up-outline"
                  style={{
                    fontSize: "24px",
                    marginLeft: "8px",
                  }}
                ></ion-icon>
              </p>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
