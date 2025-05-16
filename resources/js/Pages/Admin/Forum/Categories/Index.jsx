import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Table, Space, Button, Modal, message } from 'antd';
import { EditOutlined, DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import AdminLayout from '@/Layouts/AdminLayout';
import CategoryForm from './CategoryForm';
import { router } from '@inertiajs/react';

export default function Categories({ categories }) {
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingCategory, setEditingCategory] = useState(null);
    const [loading, setLoading] = useState(false);

    const columns = [
        {
            title: 'ID',
            dataIndex: 'id',
            key: 'id',
            width: 80,
        },
        {
            title: 'Tên danh mục',
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: 'Mô tả',
            dataIndex: 'description',
            key: 'description',
            ellipsis: true,
        },
        {
            title: 'Thứ tự',
            dataIndex: 'arrange',
            key: 'arrange',
            width: 100,
        },
        {
            title: 'Phân quyền',
            dataIndex: 'role_restriction',
            key: 'role_restriction',
            width: 120,
        },
        {
            title: 'Số diễn đàn con',
            dataIndex: 'subforums_count',
            key: 'subforums_count',
            width: 150,
        },
        {
            title: 'Thao tác',
            key: 'action',
            width: 150,
            render: (_, record) => (
                <Space>
                    <Button
                        type="primary"
                        icon={<EditOutlined />}
                        onClick={() => handleEdit(record)}
                    >
                        Sửa
                    </Button>
                    <Button
                        danger
                        icon={<DeleteOutlined />}
                        onClick={() => handleDelete(record.id)}
                    >
                        Xóa
                    </Button>
                </Space>
            ),
        },
    ];

    const handleEdit = (category) => {
        setEditingCategory(category);
        setIsModalVisible(true);
    };

    const handleDelete = (id) => {
        Modal.confirm({
            title: 'Xác nhận xóa',
            content: 'Bạn có chắc chắn muốn xóa danh mục này?',
            onOk: async () => {
                try {
                    await router.delete(`/admin/categories/${id}`);
                    message.success('Xóa danh mục thành công');
                } catch (error) {
                    message.error('Có lỗi xảy ra khi xóa danh mục');
                }
            },
        });
    };

    const handleModalClose = () => {
        setIsModalVisible(false);
        setEditingCategory(null);
    };

    const handleFormSubmit = async (values) => {
        setLoading(true);
        try {
            if (editingCategory) {
                await router.put(`/admin/categories/${editingCategory.id}`, values);
                message.success('Cập nhật danh mục thành công');
            } else {
                await router.post('/admin/categories', values);
                message.success('Thêm danh mục thành công');
            }
            handleModalClose();
        } catch (error) {
            message.error('Có lỗi xảy ra khi lưu danh mục');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AdminLayout title="Quản lý danh mục">
            <Head title="Quản lý danh mục - Admin" />

            <div style={{ marginBottom: 16 }}>
                <Button
                    type="primary"
                    icon={<PlusOutlined />}
                    onClick={() => setIsModalVisible(true)}
                >
                    Thêm danh mục mới
                </Button>
            </div>

            <Table
                columns={columns}
                dataSource={categories.data}
                rowKey="id"
                pagination={{
                    total: categories.total,
                    pageSize: categories.per_page,
                    current: categories.current_page,
                }}
            />

            <Modal
                title={editingCategory ? "Sửa danh mục" : "Thêm danh mục mới"}
                open={isModalVisible}
                onCancel={handleModalClose}
                footer={null}
                width={600}
            >
                <CategoryForm
                    initialValues={editingCategory}
                    onSubmit={handleFormSubmit}
                    loading={loading}
                />
            </Modal>
        </AdminLayout>
    );
}
