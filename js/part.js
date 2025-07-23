// Sistema de partículas - Versión corregida
console.log('🎨 Inicializando sistema de partículas...');

// Esperar a que el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('particleCanvas');

    if (!canvas) {
        console.error('❌ Canvas no encontrado');
        return;
    }

    const ctx = canvas.getContext('2d');
    console.log('✅ Canvas encontrado e inicializado');

    // Configurar tamaño del canvas
    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resizeCanvas();

    class Particle {
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.vx = (Math.random() - 0.5) * 6;
            this.vy = (Math.random() - 0.5) * 6;
            this.size = Math.random() * 15 + 8; // Tamaño mucho más grande (8-23px)
            this.life = 1.0;
            this.decay = Math.random() * 0.015 + 0.003; // Duran más tiempo

            // Colores más vibrantes
            const colors = [
                '#d11d1dff', '#15daccff', '#6a15daff', '#15d47bff',
                '#fdc91cff', '#dd2eddff', '#3b383bff', '#f56200ff'
            ];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;
            this.life -= this.decay;

            // Gravedad sutil
            this.vy += 0.05;

            // Fricción
            this.vx *= 0.995;
            this.vy *= 0.995;

            // Rebotar en los bordes
            if (this.x <= 0 || this.x >= canvas.width) {
                this.vx *= -0.8;
                this.x = Math.max(0, Math.min(canvas.width, this.x));
            }
            if (this.y <= 0 || this.y >= canvas.height) {
                this.vy *= -0.8;
                this.y = Math.max(0, Math.min(canvas.height, this.y));
            }
        }

        draw(ctx) {
            ctx.save();
            ctx.globalAlpha = this.life;

            // Efecto de brillo más grande
            const gradient = ctx.createRadialGradient(
                this.x, this.y, 0,
                this.x, this.y, this.size * 3 // Radio de brillo más amplio
            );
            gradient.addColorStop(0, this.color);
            gradient.addColorStop(0.3, this.color + '80'); // Color semi-transparente
            gradient.addColorStop(1, 'transparent');

            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();
        }

        isDead() {
            return this.life <= 0;
        }
    }

    class ParticleSystem {
        constructor() {
            this.particles = [];
            this.maxParticles = 150; // Menos partículas para mejor performance con tamaños grandes
        }

        addParticle(x, y, count = 1) {
            for (let i = 0; i < count; i++) {
                if (this.particles.length < this.maxParticles) {
                    this.particles.push(new Particle(x, y));
                }
            }
        }

        update() {
            // Actualizar partículas
            for (let particle of this.particles) {
                particle.update();
            }

            // Remover partículas muertas
            this.particles = this.particles.filter(particle => !particle.isDead());
        }

        draw(ctx) {
            for (let particle of this.particles) {
                particle.draw(ctx);
            }
        }

        // Agregar partículas automáticas ocasionalmente
        addRandomParticles() {
            if (Math.random() < 0.005) { // 0.5% de probabilidad
                this.addParticle(
                    Math.random() * canvas.width,
                    Math.random() * canvas.height,
                    1
                );
            }
        }

        getParticleCount() {
            return this.particles.length;
        }
    }

    const particleSystem = new ParticleSystem();
    let animationId;

    function animate() {
        // Limpiar canvas con transparencia
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Fondo sutil con gradiente
        const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
        gradient.addColorStop(0, 'rgba(102, 126, 234, 0.03)');
        gradient.addColorStop(1, 'rgba(118, 75, 162, 0.03)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Agregar partículas aleatorias
        particleSystem.addRandomParticles();

        // Actualizar y dibujar
        particleSystem.update();
        particleSystem.draw(ctx);

        animationId = requestAnimationFrame(animate);
    }

    // Event listeners
    window.addEventListener('resize', resizeCanvas);

    // Clic en canvas para agregar partículas
    canvas.addEventListener('click', (e) => {
        console.log('🎆 Clic detectado, agregando partículas');
        particleSystem.addParticle(e.clientX, e.clientY, 12); // Menos partículas pero más grandes
    });

    // Área interactiva específica
    const interactiveArea = document.querySelector('.particle-interactive');
    if (interactiveArea) {
        interactiveArea.addEventListener('click', (e) => {
            console.log('✨ Área interactiva activada');
            const rect = interactiveArea.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            // Explosión de partículas más grandes
            for (let i = 0; i < 15; i++) {
                particleSystem.addParticle(centerX, centerY, 1);
            }
        });
    }

    // Movimiento del mouse (opcional, más sutil)
    let mouseTrail = false;
    canvas.addEventListener('mousemove', (e) => {
        if (mouseTrail && Math.random() < 0.1) {
            particleSystem.addParticle(e.clientX, e.clientY, 1);
        }
    });

    // Toggle para el rastro del mouse
    document.addEventListener('keydown', (e) => {
        if (e.key === 't' || e.key === 'T') {
            mouseTrail = !mouseTrail;
            console.log(`🖱️ Rastro del mouse: ${mouseTrail ? 'ON' : 'OFF'}`);
        }
    });

    // Iniciar animación
    console.log('🚀 Iniciando animación de partículas');
    animate();

    // Debug info cada 5 segundos
    setInterval(() => {
        console.log(`📊 Partículas activas: ${particleSystem.getParticleCount()}`);
    }, 5000);
});