import React from "react";
import { Form, Input, Select, Switch, Button } from "antd";

const { TextArea } = Input;
const { Option } = Select;

export default function SubforumForm({
    initialValues,
    onSubmit,
    loading,
    categories,
    moderators,
}) {
    const [form] = Form.useForm();

    React.useEffect(() => {
        if (initialValues) {
            form.setFieldsValue({
                ...initialValues,
                main_category_id: initialValues.main_category?.id,
                moderator_id: initialValues.moderator?.id,
            });
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
                active: true,
                pinned: false,
                role_restriction: "public",
                ...initialValues,
            }}
        >
            <Form.Item
                name="name"
                label="Tên diễn đàn"
                rules={[
                    { required: true, message: "Vui lòng nhập tên diễn đàn" },
                ]}
            >
                <Input placeholder="Nhập tên diễn đàn" />
            </Form.Item>

            <Form.Item
                name="description"
                label="Mô tả"
                rules={[{ required: true, message: "Vui lòng nhập mô tả" }]}
            >
                <TextArea rows={4} placeholder="Nhập mô tả diễn đàn" />
            </Form.Item>

            <Form.Item
                name="main_category_id"
                label="Danh mục"
                rules={[{ required: true, message: "Vui lòng chọn danh mục" }]}
            >
                <Select placeholder="Chọn danh mục">
                    {categories.map((category) => (
                        <Option key={category.id} value={category.id}>
                            {category.name}
                        </Option>
                    ))}
                </Select>
            </Form.Item>

            <Form.Item
                name="moderator_id"
                label="Người điều hành"
                rules={[
                    {
                        required: true,
                        message: "Vui lòng chọn người điều hành",
                    },
                ]}
            >
                <Select placeholder="Chọn người điều hành">
                    {moderators.map((moderator) => (
                        <Option key={moderator.id} value={moderator.id}>
                            {moderator.username}
                        </Option>
                    ))}
                </Select>
            </Form.Item>

            <Form.Item
                name="role_restriction"
                label="Phân quyền"
                rules={[
                    { required: true, message: "Vui lòng chọn phân quyền" },
                ]}
            >
                <Select placeholder="Chọn phân quyền">
                    <Option value="public">Công khai</Option>
                    <Option value="member">Thành viên</Option>
                    <Option value="moderator">Điều hành viên</Option>
                    <Option value="admin">Quản trị viên</Option>
                </Select>
            </Form.Item>

            <Form.Item
                name="active"
                valuePropName="checked"
                label="Trạng thái hoạt động"
            >
                <Switch />
            </Form.Item>

            <Form.Item
                name="pinned"
                valuePropName="checked"
                label="Ghim diễn đàn"
            >
                <Switch />
            </Form.Item>

            <Form.Item>
                <Button
                    type="primary"
                    htmlType="submit"
                    loading={loading}
                    block
                >
                    {initialValues ? "Cập nhật" : "Thêm mới"}
                </Button>
            </Form.Item>
        </Form>
    );
}
