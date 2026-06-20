const BASE_URL = 'https://你的域名.com/'; // 修改为你的网站地址

document.addEventListener('DOMContentLoaded', async () => {
  const urlInput = document.getElementById('url');
  const titleInput = document.getElementById('title');
  const categorySelect = document.getElementById('category');
  const addBtn = document.getElementById('addBtn');
  const msgDiv = document.getElementById('message');
  const settingsBtn = document.getElementById('settingsBtn');
  const apiKeyPanel = document.getElementById('apiKeyPanel');
  const apiKeyInput = document.getElementById('apiKey');
  const saveApiKeyBtn = document.getElementById('saveApiKeyBtn');

  let apiKey = localStorage.getItem('bm_api_key') || '';

  // 显示已保存密钥
  apiKeyInput.value = apiKey;

  // 获取当前标签页信息
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (tab) {
    urlInput.value = tab.url;
    titleInput.value = tab.title;
  }

  // 显示/隐藏密钥设置面板
  settingsBtn.addEventListener('click', () => {
    apiKeyPanel.style.display = apiKeyPanel.style.display === 'none' ? 'block' : 'none';
  });

  saveApiKeyBtn.addEventListener('click', () => {
    const newKey = apiKeyInput.value.trim();
    localStorage.setItem('bm_api_key', newKey);
    apiKey = newKey;
    showMessage('API 密钥已保存', 'success');
  });

  // 加载分类列表，需要携带密钥（如果未登录）
  async function loadCategories() {
    let url = `${BASE_URL}/api_categories.php`;
    if (apiKey) url += `?api_key=${encodeURIComponent(apiKey)}`;
    try {
        const res = await fetch(url, { credentials: 'include' });
        if (!res.ok) throw new Error('未授权：请先登录网站或设置 API 密钥');
        const data = await res.json();
        // 兼容新格式 { categories, default_category_id } 或旧数组
        let categories = Array.isArray(data) ? data : data.categories;
        let defaultCatId = Array.isArray(data) ? null : data.default_category_id;

        categorySelect.innerHTML = '';
        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            categorySelect.appendChild(opt);
        });

        // 设置默认分类
        if (defaultCatId) {
            // 检查该分类是否存在
            if (categories.some(cat => cat.id == defaultCatId)) {
                categorySelect.value = defaultCatId;
            }
        } else if (categories.length > 0) {
            // 若无历史记录，选中第一个分类
            categorySelect.value = categories[0].id;
        }
    } catch (err) {
        showMessage(err.message, 'error');
        categorySelect.innerHTML = '<option value="">无分类</option>';
    }
}

  await loadCategories();

  // 添加书签
  addBtn.addEventListener('click', async () => {
    const title = titleInput.value.trim();
    const url = urlInput.value.trim();
    const categoryId = categorySelect.value;

    if (!title || !url) { showMessage('标题和网址不能为空', 'error'); return; }
    if (!categoryId) { showMessage('请选择一个分类', 'error'); return; }

    addBtn.disabled = true;
    const body = { title, url, category_id: categoryId };
    if (apiKey) body.api_key = apiKey;

    try {
      const res = await fetch(`${BASE_URL}/api_add_bookmark.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        credentials: 'include'
      });
      const data = await res.json();
      if (data.success) {
        showMessage('书签添加成功！', 'success');
      } else {
        showMessage(data.message || '添加失败', 'error');
      }
    } catch (err) {
      showMessage('请求失败，请检查网络或密钥是否正确', 'error');
    } finally {
      addBtn.disabled = false;
    }
  });

  function showMessage(text, type) {
    msgDiv.textContent = text;
    msgDiv.className = `message ${type}`;
    msgDiv.style.display = 'block';
    setTimeout(() => { msgDiv.style.display = 'none'; }, 3000);
  }
});
