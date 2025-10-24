// Asegurarse de que el DOM está cargado (asumiendo que jQuery está cargado)
$(document).ready(function() {
    
    // 1. Autocompletar campos al seleccionar empleado
    $('#selector_empleado').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var salario = selectedOption.data('salario');
        var ca = selectedOption.data('ca');
        
        if (salario) {
            $('#salario_bruto').val(salario);
        }
        if (ca) {
            $('#comunidad_autonoma').val(ca);
        }
    });

    // 2. Calcular al hacer clic en el botón
    $('#btn_calcular_nomina').on('click', function() {
        var salario = $('#salario_bruto').val();
        var ca = $('#comunidad_autonoma').val();

        if (!salario || !ca) {
            alert('Por favor, introduce un salario bruto y una comunidad autónoma.');
            return;
        }
        
        // Mostrar 'calculando'
        $('#resultado_nomina').css('opacity', 0.5);
        $('#res_bruto_anual').text('Calculando...');
        $('#res_irpf_estatal').text('Calculando...');
        $('#res_irpf_auto').text('Calculando...');
        $('#res_irpf_total').text('Calculando...');
        $('#res_neto_anual').text('Calculando...');
        $('#res_neto_mensual').text('Calculando...');


        // Llamada AJAX al nuevo archivo PHP
        $.ajax({
            url: 'rrhh_ajax_calculo_irpf.php',
            type: 'POST',
            dataType: 'json',
            data: {
                salario: salario,
                ca: ca
            },
            success: function(data) {
                if (data.error) {
                    alert('Error: ' + data.error);
                    $('#resultado_nomina').css('opacity', 1);
                     $('#res_bruto_anual').text('Error');
                     // ... resetear los demás campos
                } else {
                    // Rellenar la tabla de resultados
                    $('#res_bruto_anual').text(data.bruto_anual.toFixed(2) + ' €');
                    $('#res_irpf_estatal').text(data.irpf_estatal.toFixed(2) + ' €');
                    $('#res_irpf_auto').text(data.irpf_autonomico.toFixed(2) + ' €');
                    $('#res_irpf_total').text(data.irpf_total.toFixed(2) + ' € (' + data.porcentaje_total.toFixed(2) + ' %)');
                    $('#res_neto_anual').text(data.neto_anual.toFixed(2) + ' €');
                    $('#res_neto_mensual').text(data.neto_mensual.toFixed(2) + ' €');
                    
                    $('#resultado_nomina').css('opacity', 1);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('Error de conexión AJAX: ' + textStatus);
                $('#resultado_nomina').css('opacity', 1);
                $('#res_bruto_anual').text('Error AJAX');
            }
        });
    });
});