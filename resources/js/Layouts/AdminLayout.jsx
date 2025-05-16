import React from 'react';
import { Link } from '@inertiajs/react';
import { Layout, Menu, Breadcrumb } from 'antd';
import {
    DashboardOutlined,
    AppstoreOutlined,
    TeamOutlined,
    FileTextOutlined,
    SettingOutlined
} from '@ant-design/icons';

const { Header, Content, Footer, Sider } = Layout;

export default function AdminLayout({ children, title }) {
    const menuItems = [
        {
            key: 'dashboard',
            icon: <DashboardOutlined />,
            label: <Link href="/admin/dashboard">Dashboard</Link>
        },
        {
            key: 'forum',
            icon: <AppstoreOutlined />,
            label: 'Quản lý Forum',
            children: [
                {
                    key: 'categories',
                    label: <Link href="/admin/forum-categories">Danh mục</Link>
                },
                {
                    key: 'subforums',
                    label: <Link href="/admin/subforums">Diễn đàn con</Link>
                },
                {
                    key: 'posts',
                    label: <Link href="/admin/posts">Bài viết</Link>
                }
            ]
        },
        {
            key: 'users',
            icon: <TeamOutlined />,
            label: <Link href="/admin/users">Quản lý người dùng</Link>
        },
        {
            key: 'classes',
            icon: <FileTextOutlined />,
            label: 'Quản lý lớp học',
            children: [
                {
                    key: 'class-list',
                    label: <Link href="/admin/classes">Danh sách lớp</Link>
                },
                {
                    key: 'schedules',
                    label: <Link href="/admin/schedules">Thời khóa biểu</Link>
                }
            ]
        },
        {
            key: 'violations',
            icon: <SettingOutlined />,
            label: 'Quản lý vi phạm',
            children: [
                {
                    key: 'violation-list',
                    label: <Link href="/admin/violations">Danh sách vi phạm</Link>
                },
                {
                    key: 'monitor-reports',
                    label: <Link href="/admin/monitor-reports">Báo cáo xung kích</Link>
                }
            ]
        }
    ];

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider width={260} theme="light">
                <div style={{ height: 32, margin: 16, background: 'rgba(0, 0, 0, 0.2)' }} />
                <Menu
                    mode="inline"
                    defaultSelectedKeys={['dashboard']}
                    style={{ height: '100%', borderRight: 0 }}
                    items={menuItems}
                />
            </Sider>
            <Layout>
                <Header style={{ background: '#fff', padding: 0, paddingLeft: 16 }}>
                    <h1 style={{ margin: 0 }}>{title}</h1>
                </Header>
                <Content style={{ margin: '24px 16px 0' }}>
                    <div style={{ padding: 24, background: '#fff', minHeight: 360 }}>
                        {children}
                    </div>
                </Content>
                <Footer style={{ textAlign: 'center' }}>
                    CBH Youth Online Admin ©{new Date().getFullYear()}
                </Footer>
            </Layout>
        </Layout>
    );
}
