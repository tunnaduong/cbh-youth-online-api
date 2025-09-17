import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

export default function Show({ auth, profile }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Profile" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex items-start space-x-6">
                            <img
                                src={profile.avatar}
                                alt="Profile Avatar"
                                className="w-24 h-24 rounded-full"
                            />
                            <div>
                                <h2 className="text-2xl font-bold">
                                    {profile.profile_name || profile.username}
                                </h2>
                                <p className="text-gray-500">
                                    @{profile.username}
                                </p>
                                <p className="text-sm text-gray-500">
                                    Joined {profile.joined_at}
                                </p>
                                {profile.bio && (
                                    <p className="mt-4 text-gray-700">
                                        {profile.bio}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-8">
                            <h3 className="text-lg font-semibold mb-4">
                                Posts
                            </h3>
                            <div className="space-y-4">
                                {profile.posts.map((post) => (
                                    <div
                                        key={post.id}
                                        className="border-b pb-4"
                                    >
                                        <h4 className="font-medium">
                                            {post.title}
                                        </h4>
                                        <p className="text-sm text-gray-500">
                                            {post.created_at}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
