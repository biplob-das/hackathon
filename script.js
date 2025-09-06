document.addEventListener('DOMContentLoaded', () => {
    const sections = document.querySelectorAll('main section');
    const navItems = document.querySelectorAll('nav ul li');

    // Function to hide all sections and show the selected one
    function showSection(sectionId) {
        sections.forEach(section => {
            section.classList.add('hidden');
            if (section.id === sectionId) {
                section.classList.remove('hidden');
            }
        });
        // Update active nav item
        navItems.forEach(item => {
            item.classList.remove('bg-purple-700');
            if (item.dataset.section === sectionId) {
                item.classList.add('bg-purple-700');
            }
        });
    }

    // Add click event listeners to nav items
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            const sectionId = item.dataset.section;
            showSection(sectionId);
        });
    });

    // Fetch tasks for To-Do section
    async function fetchTasks() {
        try {
            const response = await axios.get('index.php?ajax=tasks');
            return response.data;
        } catch (error) {
            console.error('Error fetching tasks:', error);
            return [];
        }
    }

    // Update To-Do section UI
    async function updateTodoUI() {
        const tasks = await fetchTasks();
        const taskList = document.querySelector('#todo ul');
        taskList.innerHTML = '';
        tasks.forEach(task => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between';
            li.innerHTML = `
                <span>${task.description}</span>
                ${!task.completed ? `
                <form method="post" style="display:inline">
                    <input type="hidden" name="complete_task" value="1">
                    <input type="hidden" name="task_id" value="${task.id}">
                    <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">Complete</button>
                </form>` : '<span class="text-green-500">(Completed)</span>'}
            `;
            taskList.appendChild(li);
        });
    }

    // Initial setup: Show To-Do section and update tasks
    showSection('calendar'); // Default to calendar
    updateTodoUI();
});