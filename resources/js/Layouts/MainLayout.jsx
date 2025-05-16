import React, { useState } from 'react';
import { Layout, Menu, theme, Avatar, Dropdown } from 'antd';
import {
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DashboardOutlined,
    TeamOutlined,
    AppstoreOutlined,
    MessageOutlined,
    UserOutlined,
    LogoutOutlined,
    SettingOutlined
} from '@ant-design/icons';
import { Link, usePage } from '@inertiajs/react';

const { Header, Sider, Content } = Layout;

export default function MainLayout({ children, title }) {
    const [collapsed, setCollapsed] = useState(false);
    const { auth } = usePage().props;
    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const menuItems = [
        {
            key: 'dashboard',
            icon: <DashboardOutlined />,
            label: <Link href="/admin/dashboard">Dashboard</Link>,
        },
        {
            key: 'users',
            icon: <TeamOutlined />,
            label: <Link href="/admin/users">Quản lý người dùng</Link>,
        },
        {
            key: 'forum',
            icon: <MessageOutlined />,
            label: 'Quản lý diễn đàn',
            children: [
                {
                    key: 'categories',
                    label: <Link href="/admin/categories">Danh mục</Link>,
                },
                {
                    key: 'subforums',
                    label: <Link href="/admin/subforums">Diễn đàn con</Link>,
                }
            ],
        },
        {
            key: 'classes',
            icon: <AppstoreOutlined />,
            label: <Link href="/admin/classes">Quản lý lớp học</Link>,
        }
    ];

    const userMenuItems = [
        {
            key: 'profile',
            icon: <UserOutlined />,
            label: <Link href="/profile">Thông tin cá nhân</Link>,
        },
        {
            key: 'settings',
            icon: <SettingOutlined />,
            label: <Link href="/settings">Cài đặt</Link>,
        },
        {
            type: 'divider',
        },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: <Link href="/logout" method="post" as="button">Đăng xuất</Link>,
            danger: true,
        },
    ];

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider trigger={null} collapsible collapsed={collapsed}>
                <div className="demo-logo-vertical" style={{
                    height: 32,
                    margin: 16,
                    background: 'rgba(255, 255, 255, 0.2)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: 'white',
                    fontSize: '18px',
                    fontWeight: 'bold'
                }}>
                    {collapsed ? 'CYO' : 'CBH Youth Online'}
                </div>
                <Menu
                    theme="dark"
                    mode="inline"
                    defaultSelectedKeys={['dashboard']}
                    items={menuItems}
                />
            </Sider>
            <Layout>
                <Header style={{
                    padding: 0,
                    background: colorBgContainer,
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    paddingRight: 24
                }}>
                    {React.createElement(collapsed ? MenuUnfoldOutlined : MenuFoldOutlined, {
                        className: 'trigger',
                        onClick: () => setCollapsed(!collapsed),
                        style: {
                            padding: '0 24px',
                            fontSize: '18px',
                            cursor: 'pointer'
                        }
                    })}
                    <Dropdown menu={{ items: userMenuItems }} placement="bottomRight">
                        <div style={{ cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span>{auth?.user?.name}</span>
                            <Avatar icon={<UserOutlined />} />
                        </div>
                    </Dropdown>
                </Header>
                <Content style={{
                    margin: '24px 16px',
                    padding: 24,
                    minHeight: 280,
                    background: colorBgContainer,
                    borderRadius: 8,
                }}>
                    {children}
                </Content>
            </Layout>
        </Layout>
    );
}
