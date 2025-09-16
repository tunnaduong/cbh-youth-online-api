import React from "react";
import { Form, Input, Select, Switch, Modal } from "antd";

const SubforumForm = ({
    open,
    onCancel,
    onSubmit,
    initialValues,
    categories,
    moderators,
    title,
}) => {
    const [form] = Form.useForm();

    React.useEffect(() => {
        if (open) {
            form.setFieldsValue(
                initialValues || {
                    name: "",
                    description: "",
                    main_category_id: undefined,
                    moderator_id: undefined,
                    role_restriction: undefined,
                    active: true,
                    pinned: false,
                }
            );
        }
    }, [open, initialValues]);

    const handleSubmit = () => {
        form.validateFields().then((values) => {
            onSubmit(values);
            form.resetFields();
        });
    };

    return (
        <Modal
            open={open}
            title={title}
            okText="Lưu"
            cancelText="Hủy"
            onCancel={onCancel}
            onOk={handleSubmit}
        >
            <Form form={form} layout="vertical" initialValues={initialValues}>
                <Form.Item
                    name="name"
                    label="Tên diễn đàn con"
                    rules={[
                        {
                            required: true,
                            message: "Vui lòng nhập tên diễn đàn con",
                        },
                    ]}
                >
                    <Input placeholder="Nhập tên diễn đàn con" />
                </Form.Item>

                <Form.Item name="description" label="Mô tả">
                    <Input.TextArea
                        placeholder="Nhập mô tả cho diễn đàn con"
                        rows={4}
                    />
                </Form.Item>

                <Form.Item
                    name="main_category_id"
                    label="Danh mục chính"
                    rules={[
                        {
                            required: true,
                            message: "Vui lòng chọn danh mục chính",
                        },
                    ]}
                >
                    <Select placeholder="Chọn danh mục chính">
                        {categories.map((category) => (
                            <Select.Option
                                key={category.id}
                                value={category.id}
                            >
                                {category.name}
                            </Select.Option>
                        ))}
                    </Select>
                </Form.Item>

                <Form.Item name="moderator_id" label="Người điều hành">
                    <Select placeholder="Chọn người điều hành" allowClear>
                        {moderators.map((moderator) => (
                            <Select.Option
                                key={moderator.id}
                                value={moderator.id}
                            >
                                {moderator.username}
                            </Select.Option>
                        ))}
                    </Select>
                </Form.Item>

                <Form.Item
                    name="role_restriction"
                    label="Giới hạn quyền truy cập"
                >
                    <Select placeholder="Chọn quyền truy cập" allowClear>
                        <Select.Option value="student">Học sinh</Select.Option>
                        <Select.Option value="teacher">Giáo viên</Select.Option>
                        <Select.Option value="moderator">
                            Điều hành viên
                        </Select.Option>
                        <Select.Option value="admin">
                            Quản trị viên
                        </Select.Option>
                    </Select>
                </Form.Item>

                <Form.Item
                    name="active"
                    label="Kích hoạt"
                    valuePropName="checked"
                >
                    <Switch />
                </Form.Item>

                <Form.Item name="pinned" label="Ghim" valuePropName="checked">
                    <Switch />
                </Form.Item>
            </Form>
        </Modal>
    );
};

export default SubforumForm;
