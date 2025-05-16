import React from 'react';
import { Form, Input, Select, InputNumber, Button } from 'antd';

const { TextArea } = Input;

export default function CategoryForm({ initialValues, onSubmit, loading }) {
    const [form] = Form.useForm();

    React.useEffect(() => {
        if (initialValues) {
            form.setFieldsValue(initialValues);
        } else {
            form.resetFields();
        }
    }, [initialValues]);

    const handleSubmit = async (values) => {
        await onSubmit(values);
        if (!initialValues) {
            form.resetFields();
        }
    };

    return (
        <Form
            form={form}
            layout="vertical"
            onFinish={handleSubmit}
            initialValues={{
                role_restriction: 'user',
                arrange: 0,
            }}
        >
            <Form.Item
                name="name"
                label="Tên danh mục"
                rules={[{ required: true, message: 'Vui lòng nhập tên danh mục' }]}
            >
                <Input placeholder="Nhập tên danh mục" />
            </Form.Item>

            <Form.Item
                name="description"
                label="Mô tả"
                rules={[{ required: true, message: 'Vui lòng nhập mô tả' }]}
            >
                <TextArea rows={4} placeholder="Nhập mô tả danh mục" />
            </Form.Item>

            <Form.Item
                name="arrange"
                label="Thứ tự hiển thị"
                rules={[{ required: true, message: 'Vui lòng nhập thứ tự' }]}
            >
                <InputNumber min={0} style={{ width: '100%' }} />
            </Form.Item>

            <Form.Item
                name="role_restriction"
                label="Phân quyền truy cập"
                rules={[{ required: true, message: 'Vui lòng chọn phân quyền' }]}
            >
                <Select>
                    <Select.Option value="user">Người dùng</Select.Option>
                    <Select.Option value="student">Học sinh</Select.Option>
                    <Select.Option value="teacher">Giáo viên</Select.Option>
                    <Select.Option value="admin">Admin</Select.Option>
                </Select>
            </Form.Item>

            <Form.Item
                name="background_image"
                label="Ảnh nền"
            >
                <Input placeholder="Nhập đường dẫn ảnh nền" />
            </Form.Item>

            <Form.Item>
                <Button type="primary" htmlType="submit" loading={loading} block>
                    {initialValues ? 'Cập nhật' : 'Thêm mới'}
                </Button>
            </Form.Item>
        </Form>
    );
}
