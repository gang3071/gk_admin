#!/bin/bash

# 查找控制器中未翻译的中文文本
# 使用方法：./find_untranslated.sh

CONTROLLER_DIR="addons/webman/controller"
OUTPUT_FILE="untranslated_report.txt"

echo "开始扫描控制器中未翻译的中文文本..." > "$OUTPUT_FILE"
echo "=======================================" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# 统计变量
total_files=0
files_with_chinese=0

# 查找所有包含中文的控制器文件
echo "扫描目录: $CONTROLLER_DIR" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

for file in "$CONTROLLER_DIR"/*.php; do
    ((total_files++))
    filename=$(basename "$file")

    # 检查文件是否包含中文
    if grep -q "[\u4e00-\u9fa5]" "$file"; then
        ((files_with_chinese++))
        echo "===== $filename =====" >> "$OUTPUT_FILE"

        # 查找 message_error 中的中文
        echo "" >> "$OUTPUT_FILE"
        echo "【message_error 中的中文】" >> "$OUTPUT_FILE"
        grep -n "message_error.*[\u4e00-\u9fa5]" "$file" | grep -v "admin_trans\|trans(" >> "$OUTPUT_FILE" 2>/dev/null

        # 查找 message_success 中的中文
        echo "" >> "$OUTPUT_FILE"
        echo "【message_success 中的中文】" >> "$OUTPUT_FILE"
        grep -n "message_success.*[\u4e00-\u9fa5]" "$file" | grep -v "admin_trans\|trans(" >> "$OUTPUT_FILE" 2>/dev/null

        # 查找 ->help() 中的中文
        echo "" >> "$OUTPUT_FILE"
        echo "【->help() 中的中文】" >> "$OUTPUT_FILE"
        grep -n "->help.*[\u4e00-\u9fa5]" "$file" >> "$OUTPUT_FILE" 2>/dev/null

        # 查找 ->content() 中的中文
        echo "" >> "$OUTPUT_FILE"
        echo "【->content() 中的中文】" >> "$OUTPUT_FILE"
        grep -n "->content.*[\u4e00-\u9fa5]" "$file" >> "$OUTPUT_FILE" 2>/dev/null

        # 查找 Html::markdown() 中的中文
        echo "" >> "$OUTPUT_FILE"
        echo "【Html::markdown() 中的中文】" >> "$OUTPUT_FILE"
        grep -n "Html::markdown.*[\u4e00-\u9fa5]" "$file" >> "$OUTPUT_FILE" 2>/dev/null

        echo "" >> "$OUTPUT_FILE"
        echo "-------------------------------------" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
    fi
done

# 输出统计信息
echo "" >> "$OUTPUT_FILE"
echo "=======================================" >> "$OUTPUT_FILE"
echo "扫描完成！" >> "$OUTPUT_FILE"
echo "总文件数: $total_files" >> "$OUTPUT_FILE"
echo "包含中文的文件数: $files_with_chinese" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"
echo "详细报告已保存到: $OUTPUT_FILE" >> "$OUTPUT_FILE"

# 在终端也输出统计信息
echo "扫描完成！"
echo "总文件数: $total_files"
echo "包含中文的文件数: $files_with_chinese"
echo "详细报告已保存到: $OUTPUT_FILE"
