import React, { useState, useEffect } from "react";
import { Head, Link, router } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import Modal from "@/Components/Modal";

export default function PointDeductionsIndex({ auth, deductions, users }) {
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedDeduction, setSelectedDeduction] = useState(null);
  const [formData, setFormData] = useState({
    user_id: "",
    points_deducted: "",
    reason: "",
    description: "",
    expires_at: "",
  });

  const deductionReasons = [
    "Spam Content",
    "Inappropriate Behavior",
    "Violation of Community Guidelines",
    "Harassment",
    "Fake Information",
    "Multiple Account Abuse",
    "System Abuse",
    "Other",
  ];

  const handleCreate = () => {
    router.post("/api/admin/point-deductions", formData, {
      onSuccess: () => {
        setShowCreateModal(false);
        setFormData({
          user_id: "",
          points_deducted: "",
          reason: "",
          description: "",
          expires_at: "",
        });
        router.reload();
      },
      onError: (errors) => {
        console.error("Error creating deduction:", errors);
      },
    });
  };

  const handleEdit = (deduction) => {
    setSelectedDeduction(deduction);
    setFormData({
      user_id: deduction.user_id,
      points_deducted: deduction.points_deducted,
      reason: deduction.reason,
      description: deduction.description || "",
      expires_at: deduction.expires_at ? deduction.expires_at.split("T")[0] : "",
    });
    setShowEditModal(true);
  };

  const handleUpdate = () => {
    router.put(`/api/admin/point-deductions/${selectedDeduction.id}`, formData, {
      onSuccess: () => {
        setShowEditModal(false);
        setSelectedDeduction(null);
        router.reload();
      },
    });
  };

  const handleDelete = (id) => {
    if (confirm("Are you sure you want to delete this deduction?")) {
      router.delete(`/api/admin/point-deductions/${id}`, {
        onSuccess: () => router.reload(),
      });
    }
  };

  const handleReverse = (id) => {
    if (confirm("Are you sure you want to reverse this deduction?")) {
      router.post(
        `/api/admin/point-deductions/${id}/reverse`,
        {},
        {
          onSuccess: () => router.reload(),
        }
      );
    }
  };

  return (
    <AdminLayout user={auth.user}>
      <Head title="Point Deductions Management" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-bold">Point Deductions Management</h2>
                <PrimaryButton onClick={() => setShowCreateModal(true)}>
                  Add New Deduction
                </PrimaryButton>
              </div>

              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        User
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Points Deducted
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Reason
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Admin
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Created
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {deductions.data?.map((deduction) => (
                      <tr key={deduction.id}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10">
                              <img
                                className="h-10 w-10 rounded-full"
                                src={
                                  deduction.user?.profile?.profile_picture ||
                                  "/images/default-avatar.png"
                                }
                                alt={deduction.user?.username}
                              />
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium text-gray-900">
                                {deduction.user?.username}
                              </div>
                              <div className="text-sm text-gray-500">
                                {deduction.user?.profile?.profile_name}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          -{deduction.points_deducted}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {deduction.reason}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {deduction.admin?.username}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span
                            className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                              deduction.is_active
                                ? "bg-green-100 text-green-800"
                                : "bg-red-100 text-red-800"
                            }`}
                          >
                            {deduction.is_active ? "Active" : "Inactive"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {new Date(deduction.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <div className="flex space-x-2">
                            <SecondaryButton
                              onClick={() => handleEdit(deduction)}
                              className="text-blue-600 hover:text-blue-900"
                            >
                              Edit
                            </SecondaryButton>
                            {deduction.is_active && (
                              <SecondaryButton
                                onClick={() => handleReverse(deduction.id)}
                                className="text-yellow-600 hover:text-yellow-900"
                              >
                                Reverse
                              </SecondaryButton>
                            )}
                            <SecondaryButton
                              onClick={() => handleDelete(deduction.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Delete
                            </SecondaryButton>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {deductions.data?.length === 0 && (
                <div className="text-center py-8 text-gray-500">No point deductions found.</div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Create Modal */}
      <Modal show={showCreateModal} onClose={() => setShowCreateModal(false)}>
        <div className="p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-4">Add New Point Deduction</h2>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">User</label>
              <select
                value={formData.user_id}
                onChange={(e) => setFormData({ ...formData, user_id: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="">Select User</option>
                {users?.map((user) => (
                  <option key={user.id} value={user.id}>
                    {user.username} ({user.profile?.profile_name || user.username})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Points to Deduct</label>
              <input
                type="number"
                min="1"
                max="1000"
                value={formData.points_deducted}
                onChange={(e) => setFormData({ ...formData, points_deducted: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Reason</label>
              <select
                value={formData.reason}
                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="">Select Reason</option>
                {deductionReasons.map((reason) => (
                  <option key={reason} value={reason}>
                    {reason}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Description</label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Optional description..."
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">
                Expires At (Optional)
              </label>
              <input
                type="date"
                value={formData.expires_at}
                onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>

          <div className="flex justify-end space-x-3 mt-6">
            <SecondaryButton onClick={() => setShowCreateModal(false)}>Cancel</SecondaryButton>
            <PrimaryButton onClick={handleCreate}>Create Deduction</PrimaryButton>
          </div>
        </div>
      </Modal>

      {/* Edit Modal */}
      <Modal show={showEditModal} onClose={() => setShowEditModal(false)}>
        <div className="p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-4">Edit Point Deduction</h2>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Points to Deduct</label>
              <input
                type="number"
                min="1"
                max="1000"
                value={formData.points_deducted}
                onChange={(e) => setFormData({ ...formData, points_deducted: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Reason</label>
              <select
                value={formData.reason}
                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              >
                {deductionReasons.map((reason) => (
                  <option key={reason} value={reason}>
                    {reason}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Description</label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Optional description..."
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">
                Expires At (Optional)
              </label>
              <input
                type="date"
                value={formData.expires_at}
                onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>

          <div className="flex justify-end space-x-3 mt-6">
            <SecondaryButton onClick={() => setShowEditModal(false)}>Cancel</SecondaryButton>
            <PrimaryButton onClick={handleUpdate}>Update Deduction</PrimaryButton>
          </div>
        </div>
      </Modal>
    </AdminLayout>
  );
}
