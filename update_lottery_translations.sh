#!/bin/bash

# 更新英文翻译 - prize_level_fields
sed -i "/        'prize_count' => 'Prize Count',/a\\        'won_count' => 'Won Count',  \/\/ ⭐ Added\\n        'remaining_count' => 'Remaining',  \/\/ ⭐ Added" D:/gk_admin/addons/webman/lang/en/lottery_ticket.php

# 更新英文翻译 - filter
sed -i "/    \/\/ Validation/i\\    \/\/ Filter ⭐ Added\\n    'filter' => [\\n        'time_range' => 'Time Range',\\n        'create_time_range' => 'Create Time Range',\\n        'activity_time_range' => 'Activity Time Range',\\n    ],\\n" D:/gk_admin/addons/webman/lang/en/lottery_ticket.php

# 更新日文翻译 - prize_level_fields
sed -i "/        'prize_count' => '賞品数量',/a\\        'won_count' => '当選数',  \/\/ ⭐ 追加\\n        'remaining_count' => '残数',  \/\/ ⭐ 追加" D:/gk_admin/addons/webman/lang/jp/lottery_ticket.php

# 更新日文翻译 - filter
sed -i "/    \/\/ 検証メッセージ/i\\    \/\/ フィルター ⭐ 追加\\n    'filter' => [\\n        'time_range' => '時間範囲',\\n        'create_time_range' => '作成時間範囲',\\n        'activity_time_range' => '活動時間範囲',\\n    ],\\n" D:/gk_admin/addons/webman/lang/jp/lottery_ticket.php

echo "翻译更新完成！"
