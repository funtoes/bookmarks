// ========== 获取网页标题功能 ==========
document.addEventListener('DOMContentLoaded', function() {
    const fetchBtn = document.getElementById('fetchTitleBtn');
    if (fetchBtn) {
        fetchBtn.addEventListener('click', function() {
            const urlInput = document.getElementById('url');
            const titleInput = document.getElementById('title');
            const url = urlInput.value.trim();

            if (!url) {
                alert('请先输入网址');
                return;
            }

            fetchBtn.disabled = true;
            fetchBtn.textContent = '获取中...';

            fetch('get_title.php?url=' + encodeURIComponent(url))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.title) {
                        titleInput.value = data.title;
                    } else {
                        alert('无法获取标题，请手动输入');
                    }
                })
                .catch(() => {
                    alert('请求失败，请检查网络或手动输入标题');
                })
                .finally(() => {
                    fetchBtn.disabled = false;
                    fetchBtn.textContent = '获取标题';
                });
        });
    }

    // ========== 分类拖拽排序（增强版） ==========
    const list = document.getElementById('sortable-list');
    if (list) {
        let draggedItem = null;

        // 拖拽开始
        list.addEventListener('dragstart', function(e) {
            draggedItem = e.target.closest('.category-item');
            if (!draggedItem) return;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
            draggedItem.classList.add('dragging');
        });

        // 拖拽结束
        list.addEventListener('dragend', function(e) {
            if (draggedItem) {
                draggedItem.classList.remove('dragging');
                draggedItem = null;
            }
        });

        // 允许放置
        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            const currentDragging = document.querySelector('.category-item.dragging');
            if (!currentDragging) return;
            if (afterElement == null) {
                list.appendChild(currentDragging);
            } else {
                list.insertBefore(currentDragging, afterElement);
            }
        });

        // 放置后保存顺序（带完整反馈）
        list.addEventListener('drop', function(e) {
            e.preventDefault();
            const items = [...list.querySelectorAll('.category-item')];
            const order = items.map(item => parseInt(item.dataset.id, 10)).filter(id => !isNaN(id));

            // 安全检查：必须有分类项且数量一致
            if (order.length === 0 || order.length !== items.length) {
                showSortMessage('排序数据异常，请刷新页面', 'error');
                return;
            }

            showSortMessage('正在保存排序...', 'info');

            fetch('update_sort.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            })
            .then(async res => {
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || `服务器错误 (${res.status})`);
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    showSortMessage('排序已保存', 'success');
                } else {
                    showSortMessage(data.message || '排序保存失败', 'error');
                }
            })
            .catch(err => {
                console.error('排序保存失败:', err);
                showSortMessage(err.message || '网络错误，排序未保存', 'error');
            });
        });

        // 获取鼠标下方最近的元素，用于确定插入位置
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.category-item:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        /**
         * 在分类列表上方显示操作提示（自动消失）
         */
        function showSortMessage(text, type) {
            const oldMsg = document.querySelector('.sort-message');
            if (oldMsg) oldMsg.remove();

            const msg = document.createElement('div');
            msg.className = `flash-message flash-${type} sort-message`;
            msg.textContent = text;

            const listElem = document.getElementById('sortable-list');
            if (!listElem) {
                alert(text);
                return;
            }
            listElem.parentNode.insertBefore(msg, listElem);

            const duration = type === 'error' ? 5000 : 3000;
            setTimeout(() => {
                if (msg.parentNode) msg.remove();
            }, duration);
        }
    }

    // ========== 分类编辑模态框 ==========
    const modal = document.getElementById('editModal');
    const closeBtn = document.querySelector('.close');
    const editBtns = document.querySelectorAll('.btn-edit');
    const editId = document.getElementById('edit-id');
    const editName = document.getElementById('edit-name');

    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            editId.value = this.dataset.id;
            editName.value = this.dataset.name;
            modal.style.display = 'block';
        });
    });

    if (closeBtn) {
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
	
	// 自定义确认弹窗（替换原生 confirm）
function showConfirm(message, callback) {
    // 移除已有弹窗
    const old = document.querySelector('.confirm-overlay');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
        <div class="confirm-dialog">
            <div class="confirm-icon">⚠️</div>
            <div class="confirm-title">确认操作</div>
            <div class="confirm-message">${message}</div>
            <div class="confirm-actions">
                <button class="confirm-btn cancel">取消</button>
                <button class="confirm-btn danger">确定删除</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    overlay.querySelector('.confirm-btn.cancel').addEventListener('click', () => {
        overlay.remove();
    });
    overlay.querySelector('.confirm-btn.danger').addEventListener('click', () => {
        overlay.remove();
        if (typeof callback === 'function') callback();
    });
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });
}

// 绑定所有带有 data-confirm 属性的删除按钮
document.addEventListener('click', function(e) {
    const target = e.target.closest('[data-confirm]');
    if (!target) return;
    e.preventDefault();

    const message = target.dataset.confirm || '确定删除吗？';
    showConfirm(message, () => {
        // 如果是链接，直接跳转
        if (target.tagName === 'A') {
            window.location.href = target.href;
        }
        // 如果是表单内的按钮，提交所在表单
        if (target.tagName === 'BUTTON' && target.form) {
            target.form.submit();
        }
    });
});

// 全站统一搜索逻辑（绑定到所有 .search-form）
document.addEventListener('submit', function(e) {
    const form = e.target.closest('.search-form');
    if (!form) return;

    const engine = form.querySelector('.search-engine');
    const input = form.querySelector('.search-input');
    if (!engine || !input) return;

    const query = input.value.trim();
    if (!query) return;

    const engineValue = engine.value;
    let searchUrl = '';

    switch (engineValue) {
        case 'site':
            // 搜索书签：跳转到首页
            searchUrl = 'index.php?search=' + encodeURIComponent(query);
            break;
        case 'memo':
            // 搜索备忘录：跳转到备忘录页
            searchUrl = 'memos.php?search=' + encodeURIComponent(query);
            break;
        case 'baidu':
            searchUrl = 'https://www.baidu.com/s?wd=' + encodeURIComponent(query);
            break;
        case 'google':
            searchUrl = 'https://www.google.com/search?q=' + encodeURIComponent(query);
            break;
        case 'bing':
            searchUrl = 'https://www.bing.com/search?q=' + encodeURIComponent(query);
            break;
        case 'sogou':
            searchUrl = 'https://www.sogou.com/web?query=' + encodeURIComponent(query);
            break;
        case 'so360':
            searchUrl = 'https://www.so.com/s?q=' + encodeURIComponent(query);
            break;
        case 'duckduckgo':
            searchUrl = 'https://duckduckgo.com/?q=' + encodeURIComponent(query);
            break;
        case 'yandex':
            searchUrl = 'https://yandex.com/search/?text=' + encodeURIComponent(query);
            break;
		case 'yaru':
            searchUrl = 'https://ya.ru/search/?text=' + encodeURIComponent(query);
            break;
    }

    e.preventDefault();
    if (engineValue === 'site' || engineValue === 'memo') {
        // 内部搜索：跳转到对应页面
        window.location.href = searchUrl;
    } else {
        // 外部搜索引擎：新标签页打开
        window.open(searchUrl, '_blank');
    }
});

/**
 * 显示备忘录复制底部提示弹窗
 * @param {string} message - 提示文字
 * @param {string} type - 'success' | 'error' | '' (默认灰底)
 */
function showToast(message, type = '') {
    const old = document.querySelector('.toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icons = { success: '✅', error: '❌' };
    toast.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ️'}</span>${message}`;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
    }, 2500);
}

// 搜索引擎切换时，更新搜索框 placeholder
const engineSelects = document.querySelectorAll('.search-engine');
engineSelects.forEach(select => {
    // 定义各引擎对应的提示文字
    const placeholders = {
        'site': '搜索书签...',
        'memo': '搜索备忘录...',
        'baidu': '百度搜索',
        'google': '谷歌搜索',
        'bing': '必应搜索',
        'sogou': '搜狗搜索',
        'so360': '360搜索',
        'duckduckgo': 'DuckDuckGo搜索',
        'yandex': 'Yandex搜索',
		'yaru': 'Yandex俄语版'
    };

    // 更新对应搜索框的 placeholder
    const updatePlaceholder = () => {
        const searchForm = select.closest('.search-form');
        if (!searchForm) return;
        const input = searchForm.querySelector('.search-input');
        if (input) {
            input.placeholder = placeholders[select.value] || '搜索...';
        }
    };

    // 监听变化
    select.addEventListener('change', updatePlaceholder);
    // 初始设置
    updatePlaceholder();
});
});