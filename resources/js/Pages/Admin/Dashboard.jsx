import React from 'react';
import { Head } from '@inertiajs/react';
import Layout from '@/Layouts/AdminLayout';
import { Card, Row, Col, Statistic } from 'antd';
import { UserOutlined, MessageOutlined, TeamOutlined, FileTextOutlined } from '@ant-design/icons';

export default function Dashboard({ stats }) {
    return (
        <Layout>
            <Head title="Dashboard - Admin" />

            <div className="space-y-6">
                <h1 className="text-2xl font-semibold">Dashboard</h1>

                <Row gutter={16}>
                    <Col span={6}>
                        <Card>
                            <Statistic
                                title="Tổng số tài khoản"
                                value={stats?.users_count || 0}
                                prefix={<UserOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col span={6}>
                        <Card>
                            <Statistic
                                title="Bài viết diễn đàn"
                                value={stats?.topics_count || 0}
                                prefix={<MessageOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col span={6}>
                        <Card>
                            <Statistic
                                title="Lớp học"
                                value={stats?.classes_count || 0}
                                prefix={<TeamOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col span={6}>
                        <Card>
                            <Statistic
                                title="Báo cáo xung kích"
                                value={stats?.reports_count || 0}
                                prefix={<FileTextOutlined />}
                            />
                        </Card>
                    </Col>
                </Row>
            </div>
        </Layout>
    );
}
