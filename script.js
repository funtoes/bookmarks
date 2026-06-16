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
});