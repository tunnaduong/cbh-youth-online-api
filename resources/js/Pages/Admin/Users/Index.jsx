import React from 'react';
import { Head } from '@inertiajs/react';
import { Table, Space, Button, Tag, Modal, Card } from 'antd';
import { EditOutlined, DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import Layout from '@/Layouts/AdminLayout';

export default function Users({ users }) {
    const columns = [
        {
            title: 'ID',
            dataIndex: 'id',
            key: 'id',
            width: 80,
        },
        {
            title: 'Username',
            dataIndex: 'username',
            key: 'username',
        },
        {
            title: 'Email',
            dataIndex: 'email',
            key: 'email',
        },
        {
            title: 'Role',
            dataIndex: 'role',
            key: 'role',
            render: (role) => (
                <Tag color={role === 'admin' ? 'red' : role === 'moderator' ? 'blue' : 'green'}>
                    {role}
                </Tag>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'active',
            key: 'active',
            render: (active) => (
                <Tag color={active ? 'green' : 'red'}>
                    {active ? 'Active' : 'Inactive'}
                </Tag>
            ),
        },
        {
            title: 'Actions',
            key: 'action',
            width: 150,
            render: (_, record) => (
                <Space>
                    <Button
                        type="primary"
                        icon={<EditOutlined />}
                        href={`/admin/users/${record.id}/edit`}
                    >
                        Edit
                    </Button>
                    <Button
                        danger
                        icon={<DeleteOutlined />}
                        onClick={() => handleDelete(record.id)}
                    >
                        Delete
                    </Button>
                </Space>
            ),
        },
    ];

    const handleDelete = (id) => {
        Modal.confirm({
            title: 'Xác nhận xóa',
            content: 'Bạn có chắc chắn muốn xóa người dùng này?',
            okText: 'Xóa',
            okType: 'danger',
            cancelText: 'Hủy',
            onOk() {
                // Implement delete functionality
            },
        });
    };

    return (
        <Layout>
            <Head title="Users Management - Admin" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-semibold">Quản lý người dùng</h1>
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        href="/admin/users/create"
                    >
                        Thêm người dùng mới
                    </Button>
                </div>

                <Card>
                    <Table
                        columns={columns}
                        dataSource={users?.data || []}
                        rowKey="id"
                        pagination={{
                            total: users?.total,
                            pageSize: users?.per_page,
                            current: users?.current_page,
                        }}
                    />
                </Card>
            </div>
        </Layout>
    );
}
