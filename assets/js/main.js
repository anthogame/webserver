// Gestion du thème
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Mettre à jour l'icône
    const themeIcon = document.querySelector('#theme-toggle i');
    themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}

// Charger le thème sauvegardé
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.body.setAttribute('data-theme', savedTheme);
    
    const themeIcon = document.querySelector('#theme-toggle i');
    if (themeIcon) {
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
});

// Gestionnaire d'événements pour le bouton de thème
document.addEventListener('click', function(e) {
    if (e.target.closest('#theme-toggle')) {
        toggleTheme();
    }
});

// API Helper
class DofusAPI {
    constructor() {
        this.baseURL = '/api/';
    }
    
    async request(endpoint, method = 'GET', data = null) {
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': 'your_api_key'
            }
        };
        
        if (data) {
            config.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(this.baseURL + endpoint, config);
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    async getCharacters() {
        return this.request('characters.php');
    }
    
    async simulateAction(action, characterId, data) {
        return this.request('actions.php', 'POST', {
            action: action,
            character_id: characterId,
            ...data
        });
    }
}

// Instance globale de l'API
const api = new DofusAPI();

// Fonctions utilitaires
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

function formatNumber(num) {
    return new Intl.NumberFormat('fr-FR').format(num);
}

function timeAgo(date) {
    const now = new Date();
    const past = new Date(date);
    const diffInSeconds = Math.floor((now - past) / 1000);
    
    if (diffInSeconds < 60) return 'À l\'instant';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} h`;
    return `${Math.floor(diffInSeconds / 86400)} j`;
}

// Gestion des actions de personnage
async function simulateHarvest(characterId, resourceId, quantity) {
    try {
        const result = await api.simulateAction('harvest', characterId, {
            resource_id: resourceId,
            quantity: quantity
        });
        
        if (result.success) {
            showNotification(result.message, 'success');
            // Recharger les données du personnage
            location.reload();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Erreur lors de la simulation', 'error');
    }
}

async function simulateCombat(characterId, monsterId) {
    try {
        const result = await api.simulateAction('combat', characterId, {
            monster_id: monsterId
        });
        
        if (result.success) {
            showNotification(result.message, 'success');
            location.reload();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Erreur lors du combat', 'error');
    }
}

// Animations
function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        element.textContent = Math.floor(current);
        
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            element.textContent = end;
            clearInterval(timer);
        }
    }, 16);
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter la classe fade-in aux éléments
    document.querySelectorAll('.card, .character-card').forEach(el => {
        el.classList.add('fade-in');
    });
    
    // Animer les statistiques
    document.querySelectorAll('[data-animate-value]').forEach(el => {
        const value = parseInt(el.textContent);
        animateValue(el, 0, value, 1000);
    });
});