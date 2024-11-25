<?php
    $blocked_schedule = get_option('geoblocking_blocked_schedule', []);
    $days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
?>
<div class="wrap">
        <h2>Geoblocking Plugin</h2>
        <p>Configure the settings for geoblocking below.</p>
        <form method="post" action="options.php">
            <?php settings_fields('geoblocking_settings_group'); ?>
            <?php do_settings_sections('geoblocking_settings_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="geoblocking_iframe_main">Iframe Principal</label></th>
                    <td><textarea name="geoblocking_iframe_main" id="geoblocking_iframe_main" rows="5" cols="50"><?php echo esc_textarea(get_option('geoblocking_iframe_main', '')); ?></textarea></td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="geoblocking_iframe_alternate">Iframe Alternativo</label></th>
                    <td><textarea name="geoblocking_iframe_alternate" id="geoblocking_iframe_alternate" rows="5" cols="50"><?php echo esc_textarea(get_option('geoblocking_iframe_alternate', '')); ?></textarea></td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="geoblocking_allowed_countries">Países permitidos en durante el bloqueo (ISO Codes)</label></th>
                    <td><input type="text" name="geoblocking_allowed_countries" id="geoblocking_allowed_countries" value="<?php echo esc_attr(get_option('geoblocking_allowed_countries', '')); ?>" size="50" />
                    <p class="description">Introduce los códigos ISO separados por comas (ejemplo: US,CA,MX).</p></td>
                </tr>

            </table>
            <h3>Horarios Bloqueados</h3>
            <p>Define los horarios bloqueados para cada día de la semana.</p>
            <table class="form-table">
                <?php foreach ($days as $day): ?>
                <tr valign="top">
                    <th scope="row"><?php echo $day; ?></th>
                    <td>
                        <div id="schedule_<?php echo strtolower($day); ?>">
                            <?php
                            $day_schedule = isset($blocked_schedule[$day]) ? $blocked_schedule[$day] : [];
                            foreach ($day_schedule as $index => $time_range): ?>
                                <div>
                                    <label>Inicio:</label>
                                    <input type="time" name="geoblocking_blocked_schedule[<?php echo $day; ?>][<?php echo $index; ?>][start]" value="<?php echo esc_attr($time_range['start']); ?>" />
                                    <label>Fin:</label>
                                    <input type="time" name="geoblocking_blocked_schedule[<?php echo $day; ?>][<?php echo $index; ?>][end]" value="<?php echo esc_attr($time_range['end']); ?>" />
                                    <button type="button" onclick="this.parentElement.remove();">Eliminar</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addTimeRange('<?php echo $day; ?>')">Añadir horario</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>Configuración de API</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="geoblocking_livestream_id">ID de Livestream</label></th>
                    <td><input type="text" name="geoblocking_livestream_id" id="geoblocking_livestream_id" value="<?php echo esc_attr(get_option('geoblocking_livestream_id', '')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="geoblocking_api_key">API Key</label></th>
                    <td><input type="text" name="geoblocking_api_key" id="geoblocking_api_key" value="<?php echo esc_attr(get_option('geoblocking_api_key', '')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="geoblocking_api_secret">API Secret</label></th>
                    <td>
                        <input type="password" name="geoblocking_api_secret" id="geoblocking_api_secret" value="<?php echo esc_attr((get_option('geoblocking_api_secret', ''))); ?>" size="50" />
                        <p class="description">El API Secret se almacena de forma cifrada.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        function addTimeRange(day) {
            const container = document.getElementById('schedule_' + day.toLowerCase());
            const index = container.children.length;
            const div = document.createElement('div');
            div.innerHTML = `
                <label>Inicio:</label>
                <input type="time" name="geoblocking_blocked_schedule[${day}][${index}][start]" />
                <label>Fin:</label>
                <input type="time" name="geoblocking_blocked_schedule[${day}][${index}][end]" />
                <button type="button" onclick="this.parentElement.remove();">Eliminar</button>
            `;
            container.appendChild(div);
        }
    </script>