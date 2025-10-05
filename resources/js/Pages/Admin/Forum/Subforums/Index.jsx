import React, { useState } from "react";
import { Head } from "@inertiajs/react";
import { Table, Space, Button, Modal, message, Tag } from "antd";
import { EditOutlined, DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import AdminLayout from "@/Layouts/AdminLayout";
import SubforumForm from "./SubforumForm";
import { router } from "@inertiajs/react";

export default function Subforums({ subforums, categories, moderators }) {
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingSubforum, setEditingSubforum] = useState(null);
  const [loading, setLoading] = useState(false);

  const columns = [
    {
      title: "ID",
      dataIndex: "id",
      key: "id",
      width: 80,
    },
    {
      title: "Tên diễn đàn",
      dataIndex: "name",
      key: "name",
    },
    {
      title: "Danh mục",
      dataIndex: "main_category",
      key: "main_category",
      render: (mainCategory) => mainCategory?.name,
    },
    {
      title: "Mô tả",
      dataIndex: "description",
      key: "description",
      ellipsis: true,
    },
    {
      title: "Trạng thái",
      key: "status",
      width: 120,
      render: (_, record) => {
        const tags = [];
        if (record.active) {
          tags.push(
            <Tag key="active" color="green">
              Hoạt động
            </Tag>
          );
        }
        if (record.pinned) {
          tags.push(
            <Tag key="pinned" color="blue">
              Ghim
            </Tag>
          );
        }
        return <Space>{tags}</Space>;
      },
    },
    {
      title: "Phân quyền",
      dataIndex: "role_restriction",
      key: "role_restriction",
      width: 120,
    },
    {
      title: "Số bài viết",
      dataIndex: "topics_count",
      key: "topics_count",
      width: 120,
    },
    {
      title: "Thao tác",
      key: "action",
      width: 150,
      render: (_, record) => (
        <Space>
          <Button type="primary" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            Sửa
          </Button>
          <Button danger icon={<DeleteOutlined />} onClick={() => handleDelete(record.id)}>
            Xóa
          </Button>
        </Space>
      ),
    },
  ];

  const handleEdit = (subforum) => {
    setEditingSubforum(subforum);
    setIsModalVisible(true);
  };

  const handleDelete = (id) => {
    Modal.confirm({
      title: "Xác nhận xóa",
      content: "Bạn có chắc chắn muốn xóa diễn đàn này?",
      onOk: async () => {
        try {
          await router.delete(`/admin/subforums/${id}`, {}, { showProgress: false });
          message.success("Xóa diễn đàn thành công");
        } catch (error) {
          message.error("Có lỗi xảy ra khi xóa diễn đàn");
        }
      },
    });
  };

  const handleModalClose = () => {
    setIsModalVisible(false);
    setEditingSubforum(null);
  };

  const handleFormSubmit = async (values) => {
    setLoading(true);
    try {
      if (editingSubforum) {
        await router.put(`/admin/subforums/${editingSubforum.id}`, values, { showProgress: false });
        message.success("Cập nhật diễn đàn thành công");
      } else {
        await router.post("/admin/subforums", values, { showProgress: false });
        message.success("Thêm diễn đàn thành công");
      }
      handleModalClose();
    } catch (error) {
      message.error("Có lỗi xảy ra khi lưu diễn đàn");
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="Quản lý diễn đàn con">
      <Head title="Quản lý diễn đàn con - Admin" />

      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setIsModalVisible(true)}>
          Thêm diễn đàn mới
        </Button>
      </div>

      <Table
        columns={columns}
        dataSource={subforums.data}
        rowKey="id"
        pagination={{
          total: subforums.total,
          pageSize: subforums.per_page,
          current: subforums.current_page,
        }}
      />

      <Modal
        title={editingSubforum ? "Sửa diễn đàn" : "Thêm diễn đàn mới"}
        open={isModalVisible}
        onCancel={handleModalClose}
        footer={null}
        width={600}
      >
        <SubforumForm
          initialValues={editingSubforum}
          onSubmit={handleFormSubmit}
          loading={loading}
          categories={categories}
          moderators={moderators}
        />
      </Modal>
    </AdminLayout>
  );
}
