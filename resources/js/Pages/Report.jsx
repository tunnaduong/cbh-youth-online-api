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
                    Báo cáo
                </h2>
            }
        >
            <Head title="Báo cáo" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            Nội dung trang báo cáo ở đây.
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
