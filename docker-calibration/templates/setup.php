<?php
/**
 * Страница настроек оборудования
 */
?>

<div class="card">
    <h2>⚙️ Настройки стенда</h2>
    <p>Настройте параметры подключения к оборудованию перед началом калибровки</p>
    
    <form id="settingsForm">
        <!-- Раздел "Корректор" -->
        <fieldset style="margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <legend style="padding: 0 10px; color: var(--primary-color); font-weight: bold;">Корректор (тестируемое устройство)</legend>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="correctorPort">COM-порт:</label>
                        <select id="correctorPort" class="form-control">
                            <option value="COM1">COM1</option>
                            <option value="COM2">COM2</option>
                            <option value="COM3">COM3</option>
                            <option value="COM4">COM4</option>
                            <option value="COM5">COM5</option>
                            <option value="COM6">COM6</option>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <option value="/dev/ttyUSB1">/dev/ttyUSB1</option>
                            <option value="/dev/ttyUSB2">/dev/ttyUSB2</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="correctorBaudrate">Скорость (бит/с):</label>
                        <select id="correctorBaudrate" class="form-control">
                            <option value="9600">9600</option>
                            <option value="19200" selected>19200</option>
                            <option value="38400">38400</option>
                            <option value="57600">57600</option>
                            <option value="115200">115200</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="correctorParity">Четность:</label>
                        <select id="correctorParity" class="form-control">
                            <option value="none" selected>None</option>
                            <option value="even">Even</option>
                            <option value="odd">Odd</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 10px;">
                <span id="correctorStatus" class="status-indicator status-pending" title="Не проверено"></span>
                <span>Статус подключения</span>
            </div>
        </fieldset>
        
        <!-- Раздел "Термоизмерители (МИТ-8)" -->
        <fieldset style="margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <legend style="padding: 0 10px; color: var(--primary-color); font-weight: bold;">Термоизмерители (МИТ-8)</legend>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="mit8Channel1Port">Канал 1 (-40°C):</label>
                        <select id="mit8Channel1Port" class="form-control">
                            <option value="">Не используется</option>
                            <option value="COM1">COM1</option>
                            <option value="COM2" selected>COM2</option>
                            <option value="COM3">COM3</option>
                            <option value="COM4">COM4</option>
                            <option value="COM5">COM5</option>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <option value="/dev/ttyUSB1">/dev/ttyUSB1</option>
                        </select>
                    </div>
                    <div style="text-align: right;">
                        <span id="channel1Status" class="status-indicator status-pending"></span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="mit8Channel2Port">Канал 2 (+10°C):</label>
                        <select id="mit8Channel2Port" class="form-control">
                            <option value="">Не используется</option>
                            <option value="COM1">COM1</option>
                            <option value="COM2">COM2</option>
                            <option value="COM3" selected>COM3</option>
                            <option value="COM4">COM4</option>
                            <option value="COM5">COM5</option>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <option value="/dev/ttyUSB1">/dev/ttyUSB1</option>
                        </select>
                    </div>
                    <div style="text-align: right;">
                        <span id="channel2Status" class="status-indicator status-pending"></span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="mit8Channel3Port">Канал 3 (+60°C):</label>
                        <select id="mit8Channel3Port" class="form-control">
                            <option value="">Не используется</option>
                            <option value="COM1">COM1</option>
                            <option value="COM2">COM2</option>
                            <option value="COM3">COM3</option>
                            <option value="COM4" selected>COM4</option>
                            <option value="COM5">COM5</option>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <option value="/dev/ttyUSB1">/dev/ttyUSB1</option>
                        </select>
                    </div>
                    <div style="text-align: right;">
                        <span id="channel3Status" class="status-indicator status-pending"></span>
                    </div>
                </div>
            </div>
        </fieldset>
        
        <!-- Раздел "Калибратор давления" -->
        <fieldset style="margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <legend style="padding: 0 10px; color: var(--primary-color); font-weight: bold;">Калибратор давления</legend>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="pressureCalibratorPort">COM-порт:</label>
                        <select id="pressureCalibratorPort" class="form-control">
                            <option value="">Не используется (ручной ввод)</option>
                            <option value="COM1">COM1</option>
                            <option value="COM2">COM2</option>
                            <option value="COM3">COM3</option>
                            <option value="COM4">COM4</option>
                            <option value="COM5" selected>COM5</option>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                            <option value="/dev/ttyUSB1">/dev/ttyUSB1</option>
                        </select>
                    </div>
                    <div style="text-align: right;">
                        <span id="pressureCalibratorStatus" class="status-indicator status-pending"></span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="pressureSensorType">Тип датчика:</label>
                        <select id="pressureSensorType" class="form-control">
                            <option value="absolute" selected>Абсолютный (5 точек)</option>
                            <option value="differential">Перепад (3 точки)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="pressureRangeMin">Мин. диапазон (кПа):</label>
                        <input type="number" id="pressureRangeMin" class="form-control" value="0" step="0.1">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="pressureRangeMax">Макс. диапазон (кПа):</label>
                        <input type="number" id="pressureRangeMax" class="form-control" value="1000" step="0.1">
                    </div>
                </div>
            </div>
        </fieldset>
        
        <!-- Кнопки действий -->
        <div style="text-align: center; margin-top: 30px;">
            <button type="button" id="testConnectionBtn" class="btn btn-warning btn-large">
                🔌 Проверить связь
            </button>
            
            <button type="button" id="saveSettingsBtn" class="btn btn-primary btn-large">
                💾 Сохранить настройки
            </button>
            
            <button type="button" id="startCalibrationBtn" class="btn btn-success btn-large" disabled>
                ▶️ Начать калибровку
            </button>
        </div>
    </form>
</div>

<div class="alert alert-info">
    <strong>ℹ️ Информация:</strong> Убедитесь, что все устройства подключены к ПК и включены перед проверкой связи. 
    Зеленые индикаторы напротив каждого устройства сигнализируют о готовности к работе.
</div>
