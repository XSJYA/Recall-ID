# Recall-ID

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.1.2-blue.svg)](https://github.com/XSJYA/recall-id)
[![WordPress Tested Up To](https://img.shields.io/badge/WordPress-5.0%2B-brightgreen.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Recall-ID** 是一款强大的 WordPress 插件，可以回收利用已删除文章的 ID，同时提供数据库清理功能。增加了二次确认、数量限制和数据预览等安全特性。

## 📋 目录

- [功能特性](#功能特性)
- [安装方法](#安装方法)
- [使用指南](#使用指南)
- [安全特性](#安全特性)
- [系统要求](#系统要求)
- [常见问题](#常见问题)
- [开发计划](#开发计划)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

## ✨ 功能特性

### 核心功能
- **ID 回收** - 自动扫描并显示所有可用的文章 ID
- **一键创建** - 点击可用 ID 即可创建新文章或页面
- **详情查看** - 查看已占用 ID 的详细信息（标题、类型、状态等）
- **自定义文章类型** - 支持创建自定义文章类型的草稿

### 数据库优化
- **清理文章修订版** - 删除历史版本，释放数据库空间
- **清理自动草稿** - 删除未保存的自动草稿
- **清空回收站** - 永久删除回收站中的内容
- **安全限制** - 每次最多删除 1000 条数据，防止误操作

### 安全增强
- ✅ **二次确认机制** - 所有删除操作都需要二次确认
- ✅ **数据预览** - 清理前可预览将要删除的数据
- ✅ **数量限制** - 最大删除数量限制，保护数据库
- ✅ **权限验证** - 严格的用户权限检查
- ✅ **CSRF 防护** - 使用 WordPress nonce 验证

## 🚀 安装方法

### 手动安装
1. 下载插件 ZIP 包
2. 解压得到 `recall-id` 文件夹
3. 上传到 `/wp-content/plugins/` 目录
4. 在 WordPress 后台「插件」页面启用插件
5. 进入「工具」→「Recall-ID」开始使用


## 📖 使用指南

1. 扫描可用 ID
   - 插件会自动扫描数据库，显示所有已占用和可用的 ID
   - 点击「重新扫描」按钮可手动刷新

2. 创建内容
   - 绿色 ID：表示可用，点击即可创建新内容
   - 红色 ID：表示已占用，点击查看详情

3. 查看详情
   点击已占用的 ID 可查看：
   - 文章标题
   - 内容类型
   - 发布状态
   - 创建日期
   - 查看链接（如果已发布）

4. 清理数据库
   - 在「数据库优化」区域选择要清理的数据类型
   - 点击「清理」按钮
   - 查看数据预览和风险警告
   - 确认后执行清理

界面预览：

![](https://s41.ax1x.com/2026/03/25/peMYpVO.png)

## 🔒 安全特性

操作安全：
- 二次确认：所有删除操作都需用户再次确认
- 数据预览：删除前显示将要删除的数据样例
- 数量限制：单次最多删除 1000 条数据
- 风险警告：明确提示操作的风险和影响

权限控制：
- 需要 manage_options 权限才能清理数据库
- 需要 publish_posts 权限才能创建内容
- 使用 WordPress nonce 验证所有 AJAX 请求

数据保护：
- 所有 SQL 查询使用参数化查询
- 输出内容经过转义处理
- 不支持直接执行用户输入的 SQL

## 💻 系统要求

- WordPress: 5.0 或更高版本
- PHP: 7.2 或更高版本
- MySQL: 5.6 或更高版本（MySQL 8.0 性能更佳）
- 内存限制: 建议 128MB 以上

兼容性测试：
✅ WordPress 5.0 - 6.4
✅ PHP 7.2 - 8.2
✅ MySQL 5.6 - 8.0
✅ 多站点模式
✅ 常见缓存插件

## ❓ 常见问题

Q: 为什么有些 ID 是空缺的？
A: WordPress 的 ID 是自动递增的，删除文章后 ID 不会自动复用，导致出现空缺。

Q: 使用已删除的 ID 创建文章安全吗？
A: 完全安全。插件会检查 ID 是否已被占用，确保不会覆盖现有内容。

Q: 清理数据会影响已发布的内容吗？
A: 不会。清理功能只删除修订版、自动草稿和回收站内容，不会影响已发布的文章。

Q: 最大删除数量可以修改吗？
A: 可以通过常量修改。在 wp-config.php 中添加：
   define('RECALL_ID_MAX_DELETE', 2000); // 修改为 2000

Q: 扫描大量 ID 时页面卡顿怎么办？
A: 插件已优化性能，限制扫描范围。如果文章数量超过 1 万篇，建议：
   1. 使用缓存插件
   2. 增加 PHP 内存限制
   3. 分批清理历史数据

Q: 支持自定义文章类型吗？
A: 支持。所有公开的自定义文章类型都会显示在创建选项中。


## 🗺️ 开发计划

已完成：
[x] ID 扫描和展示
[x] 一键创建内容
[x] 数据库清理功能
[x] 二次确认机制
[x] 数据预览功能
[x] 自定义文章类型支持
[x] 响应式界面设计

计划中：
[ ] 批量创建多个 ID 的内容
[ ] ID 范围选择器
[ ] 导出可用 ID 列表
[ ] 定时自动清理
[ ] 清理日志记录
[ ] 多语言支持


## 🤝 贡献指南

欢迎贡献代码！请遵循以下流程：

1. Fork 本仓库
2. 创建特性分支 (git checkout -b feature/AmazingFeature)
3. 提交更改 (git commit -m 'Add some AmazingFeature')
4. 推送到分支 (git push origin feature/AmazingFeature)
5. 提交 Pull Request

开发规范：
- 遵循 WordPress 编码标准
- 添加必要的注释和文档
- 测试 PHP 7.2 - 8.2 兼容性
- 确保没有安全漏洞


## 📝 更新日志

1.1.2 - 2024-01-15
- 修复语法错误导致的致命问题
- 优化 MySQL 8.0 递归查询性能
- 修复 CSS SVG 链接错误
- 增强内存使用效率

1.1.1 - 2024-01-10
- 添加清理前数据预览功能
- 添加最大删除数量限制（1000条）
- 增强二次确认机制
- 优化界面响应式设计

1.1.0 - 2024-01-05
- 添加数据库清理功能
- 支持自定义文章类型
- 添加侧边栏统计信息
- 改进用户界面

1.0.0 - 2024-01-01
- 初始版本发布
- 基础 ID 扫描和创建功能


## ⚠️ 免责声明

本插件在删除数据前会提供预览和确认，但建议在使用前：
- 备份数据库
- 在测试环境先试用
- 确认操作内容

作者不对因使用本插件造成的数据丢失承担责任。


## 📄 许可证

```
本项目采用 GPL v2 或更高版本许可证。

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```


## 🙏 致谢

- 感谢所有使用和反馈的用户
- 感谢 WordPress 开源社区
- 感谢贡献代码的开发者


## 📧 联系方式
```
- 作者: XSJYA
- 网站: https://www.xsjya.com/
- GitHub: https://github.com/XSJYA
- 项目地址: https://github.com/XSJYA/Recall-ID
- 问题反馈: https://github.com/XSJYA/Recall-ID/issues
```

### 如果这个插件对你有帮助，请给个 Star ⭐ 支持一下！
