'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('clickChart');
    const dataElement = document.getElementById('chartData');

    if (!canvas || !dataElement) {
        return;
    }

    let chartData;

    try {
        chartData = JSON.parse(dataElement.textContent);
    } catch (error) {
        return;
    }

    if (
        !Array.isArray(chartData.labels) ||
        !Array.isArray(chartData.values) ||
        chartData.labels.length !== chartData.values.length ||
        chartData.values.length === 0
    ) {
        return;
    }

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    function drawChart() {
        const width = canvas.clientWidth;
        const height = 280;
        const ratio = Math.max(1, window.devicePixelRatio || 1);

        canvas.width = width * ratio;
        canvas.height = height * ratio;

        context.setTransform(ratio, 0, 0, ratio, 0, 0);

        context.clearRect(0, 0, width, height);

        const padding = {
            top: 20,
            right: 25,
            bottom: 45,
            left: 45
        };

        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;

        const values = chartData.values.map(function (value) {
            return Math.max(0, Number(value) || 0);
        });

        const maxValue = Math.max(1, ...values);

        const gridLines = 4;

        context.font = '12px Arial';
        context.textAlign = 'right';
        context.fillStyle = '#68758a';
        context.strokeStyle = '#e5eaf1';
        context.lineWidth = 1;

        // Horizontal grid lines
        for (let i = 0; i <= gridLines; i++) {
            const y = padding.top + (chartHeight / gridLines) * i;

            const labelValue = Math.round(
                maxValue - (maxValue / gridLines) * i
            );

            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();

            context.fillText(
                String(labelValue),
                padding.left - 8,
                y + 4
            );
        }

        const points = values.map(function (value, index) {
            const x = values.length === 1
                ? padding.left + chartWidth / 2
                : padding.left +
                    (chartWidth / (values.length - 1)) * index;

            const y = padding.top +
                chartHeight -
                (value / maxValue) * chartHeight;

            return { x: x, y: y };
        });

        // Draw line
        context.beginPath();

        points.forEach(function (point, index) {
            if (index === 0) {
                context.moveTo(point.x, point.y);
            } else {
                context.lineTo(point.x, point.y);
            }
        });

        context.strokeStyle = '#315efb';
        context.lineWidth = 3;
        context.lineJoin = 'round';
        context.stroke();

        // Draw points
        points.forEach(function (point) {
            context.beginPath();
            context.arc(point.x, point.y, 4, 0, Math.PI * 2);
            context.fillStyle = '#315efb';
            context.fill();
        });

        // Date labels
        context.fillStyle = '#68758a';
        context.textAlign = 'center';
        context.font = '11px Arial';

        const labelStep = Math.max(
            1,
            Math.ceil(chartData.labels.length / 6)
        );

        chartData.labels.forEach(function (label, index) {
            if (
                index % labelStep !== 0 &&
                index !== chartData.labels.length - 1
            ) {
                return;
            }

            const x = points[index].x;

            context.fillText(
                String(label).slice(5),
                x,
                height - 15
            );
        });
    }

    drawChart();

    let resizeFrame = null;

    window.addEventListener('resize', function () {
        if (resizeFrame !== null) {
            window.cancelAnimationFrame(resizeFrame);
        }

        resizeFrame = window.requestAnimationFrame(function () {
            drawChart();
            resizeFrame = null;
        });
    });
});