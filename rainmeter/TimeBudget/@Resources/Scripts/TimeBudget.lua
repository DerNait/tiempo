local demoMode = false
local connected = false
local contractError = false
local statusText = "CONECTANDO"
local lastSyncUnix = 0
local lastFooterMinute = -1

local data = {
    currentActive = 0,
    currentName = "",
    currentStartedAt = "",
    currentElapsed = 0,
    serverTime = 0,
    todayTracked = 0,
    todayElapsed = 0,
    weekTracked = 0,
    weekElapsed = 0,
    priorityName = "Sin prioridad configurada",
    priorityActual = 0,
    priorityBudget = 0,
    leakName = "",
    leakActual = 0,
    leakLimit = 0
}

local measures = {}

local function numberVariable(name, fallback)
    local value = tonumber(SKIN:GetVariable(name))
    if value == nil then return fallback or 0 end
    return value
end

local function textVariable(name, fallback)
    local value = SKIN:GetVariable(name)
    if value == nil or value == "" then return fallback or "" end
    return value
end

-- Los textos visibles viven en el .ini porque Rainmeter lee como ANSI las
-- cadenas que devuelve Lua; este archivo se mantiene en ASCII puro.
local function uiText(name, token, value)
    local text = textVariable(name, "")
    if token ~= nil then
        text = (text:gsub(token, tostring(value), 1))
    end
    return text
end

local function setOption(meter, option, value)
    SKIN:Bang("!SetOption", meter, option, tostring(value))
end

local function updateMeter(meter)
    SKIN:Bang("!UpdateMeter", meter)
end

local function clamp(value, minimum, maximum)
    if value < minimum then return minimum end
    if value > maximum then return maximum end
    return value
end

local function formatDurationSeconds(total)
    total = math.max(0, math.floor(tonumber(total) or 0))
    local hours = math.floor(total / 3600)
    local minutes = math.floor((total % 3600) / 60)
    local seconds = total % 60
    return string.format("%02d:%02d:%02d", hours, minutes, seconds)
end

local function formatMinutes(total)
    total = math.max(0, math.floor((tonumber(total) or 0) + 0.5))
    local hours = math.floor(total / 60)
    local minutes = total % 60
    if hours <= 0 then return string.format("%d min", minutes) end
    if minutes == 0 then return string.format("%d h", hours) end
    return string.format("%d h %02d min", hours, minutes)
end

local function percent(part, whole)
    part = tonumber(part) or 0
    whole = tonumber(whole) or 0
    if whole <= 0 then return 0 end
    return math.floor((part / whole) * 100 + 0.5)
end

local function currentElapsed()
    if data.currentActive ~= 1 then return 0 end
    local extra = 0
    if data.serverTime > 0 then
        extra = math.max(0, os.time() - data.serverTime)
    end
    return data.currentElapsed + extra
end

local function setBadge(text, color)
    setOption("MeterConnectionText", "Text", text)
    setOption("MeterConnectionBg", "Shape", "Rectangle 0,0,78,25,12 | Fill Color " .. color .. " | StrokeWidth 0")
    updateMeter("MeterConnectionText")
    updateMeter("MeterConnectionBg")
end

local function renderTimer()
    local timerText = data.currentActive == 1 and formatDurationSeconds(currentElapsed()) or "00:00:00"
    setOption("MeterCurrentTimer", "Text", timerText)
    setOption("MeterCompactTimer", "Text", timerText)
    updateMeter("MeterCurrentTimer")
    updateMeter("MeterCompactTimer")
end

local function renderFooter()
    local text
    if demoMode then
        text = uiText("TextDemoFooter")
    elseif connected and lastSyncUnix > 0 then
        local minutesAgo = math.max(0, math.floor((os.time() - lastSyncUnix) / 60))
        if minutesAgo == 0 then
            text = uiText("TextSyncedNow")
        else
            text = uiText("TextSyncedAgo", "{n}", minutesAgo)
        end
    elseif contractError then
        text = uiText("TextContractError")
    else
        text = uiText("TextOffline")
    end
    setOption("MeterFooter", "Text", text)
    updateMeter("MeterFooter")
end

local function renderAll()
    if demoMode then
        setBadge("DEMO", SKIN:GetVariable("AccentSoftColor"))
    elseif connected then
        setBadge(uiText("TextOnline"), SKIN:GetVariable("SuccessColor"))
    elseif statusText == "ACTUALIZANDO" or statusText == "CONECTANDO" then
        setBadge(statusText, SKIN:GetVariable("AccentSoftColor"))
    else
        setBadge("SIN RED", SKIN:GetVariable("DangerColor"))
    end

    if data.currentActive == 1 then
        local activityName = data.currentName ~= "" and data.currentName or uiText("TextUnnamedActivity")
        setOption("MeterCurrentName", "Text", activityName)
        setOption("MeterCurrentStarted", "Text", data.currentStartedAt ~= "" and uiText("TextStartedAt", "{t}", data.currentStartedAt) or uiText("TextRunning"))
        setOption("MeterCurrentTimer", "FontColor", SKIN:GetVariable("AccentColor"))
        setOption("MeterCompactName", "Text", activityName)
        setOption("MeterCompactTimer", "FontColor", SKIN:GetVariable("AccentColor"))
    else
        setOption("MeterCurrentName", "Text", uiText("TextNoActivity"))
        setOption("MeterCurrentStarted", "Text", uiText("TextStartHint"))
        setOption("MeterCurrentTimer", "FontColor", SKIN:GetVariable("MutedColor"))
        setOption("MeterCompactName", "Text", uiText("TextNoActivityShort"))
        setOption("MeterCompactTimer", "FontColor", SKIN:GetVariable("MutedColor"))
    end

    local todayPct = percent(data.todayTracked, data.todayElapsed)
    local weekPct = percent(data.weekTracked, data.weekElapsed)
    setOption("MeterTodayValue", "Text", formatMinutes(data.todayTracked))
    setOption("MeterTodayCoverage", "Text", todayPct .. "% cubierto")
    setOption("MeterWeekValue", "Text", formatMinutes(data.weekTracked))
    setOption("MeterWeekCoverage", "Text", weekPct .. "% cubierto")

    local priorityPct = percent(data.priorityActual, data.priorityBudget)
    local priorityWidth = 360 * clamp(priorityPct / 100, 0, 1)
    setOption("MeterPriorityName", "Text", data.priorityName ~= "" and data.priorityName or "Sin prioridad configurada")
    setOption("MeterPriorityValue", "Text", formatMinutes(data.priorityActual) .. " / " .. formatMinutes(data.priorityBudget))
    setOption("MeterPriorityPercent", "Text", data.priorityBudget > 0 and (priorityPct .. "% de la meta") or "Sin presupuesto")
    setOption("MeterPriorityBarFill", "Shape", string.format("Rectangle 0,0,%.1f,10,5 | Fill Color %s | StrokeWidth 0", priorityWidth, SKIN:GetVariable("AccentColor")))

    local leakPct = percent(data.leakActual, data.leakLimit)
    local leakWidth = 360 * clamp(leakPct / 100, 0, 1)
    local leakColor = leakPct > 100 and SKIN:GetVariable("DangerColor") or SKIN:GetVariable("WarningColor")
    local leakStatus
    if data.leakLimit <= 0 then
        leakStatus = uiText("TextNoLimit")
    elseif leakPct > 100 then
        leakStatus = uiText("TextExceeded", "{n}", leakPct)
    else
        leakStatus = uiText("TextOfLimit", "{n}", leakPct)
    end
    setOption("MeterLeakName", "Text", data.leakName ~= "" and data.leakName or uiText("TextNoLeakName"))
    setOption("MeterLeakValue", "Text", formatMinutes(data.leakActual) .. " / " .. formatMinutes(data.leakLimit))
    setOption("MeterLeakPercent", "Text", leakStatus)
    setOption("MeterLeakPercent", "FontColor", leakPct > 100 and SKIN:GetVariable("DangerColor") or SKIN:GetVariable("MutedColor"))
    setOption("MeterLeakBarFill", "Shape", string.format("Rectangle 0,0,%.1f,10,5 | Fill Color %s | StrokeWidth 0", leakWidth, leakColor))

    local meters = {
        "MeterCurrentName", "MeterCurrentStarted", "MeterTodayValue", "MeterTodayCoverage",
        "MeterWeekValue", "MeterWeekCoverage", "MeterPriorityName", "MeterPriorityValue",
        "MeterPriorityPercent", "MeterPriorityBarFill", "MeterLeakName", "MeterLeakValue",
        "MeterLeakPercent", "MeterLeakBarFill", "MeterCompactName"
    }
    for _, meter in ipairs(meters) do updateMeter(meter) end
    renderTimer()
    renderFooter()
    SKIN:Bang("!Redraw")
end

local function loadDemo()
    data.currentActive = numberVariable("DemoCurrentActivityActive", 1)
    data.currentName = textVariable("DemoCurrentActivityName", "Proyecto de Unity")
    data.currentStartedAt = textVariable("DemoCurrentActivityStartedAt", "10:02")
    data.currentElapsed = numberVariable("DemoCurrentActivityElapsedSeconds", 0)
    data.serverTime = os.time()
    data.todayTracked = numberVariable("DemoTodayTrackedMinutes", 0)
    data.todayElapsed = numberVariable("DemoTodayElapsedMinutes", 1)
    data.weekTracked = numberVariable("DemoWeekTrackedMinutes", 0)
    data.weekElapsed = numberVariable("DemoWeekElapsedMinutes", 1)
    data.priorityName = textVariable("DemoPriorityName", "Proyecto de Unity")
    data.priorityActual = numberVariable("DemoPriorityActualMinutes", 0)
    data.priorityBudget = numberVariable("DemoPriorityBudgetMinutes", 0)
    data.leakName = textVariable("DemoLeakName", "Doom Scrolling")
    data.leakActual = numberVariable("DemoLeakActualMinutes", 0)
    data.leakLimit = numberVariable("DemoLeakLimitMinutes", 0)
    connected = true
    lastSyncUnix = os.time()
end

local function cacheMeasures()
    local names = {
        ok = "MeasureApiOk", serverTime = "MeasureServerTime", currentActive = "MeasureCurrentActive",
        currentName = "MeasureCurrentName", currentStartedAt = "MeasureCurrentStartedAt",
        currentElapsed = "MeasureCurrentElapsed", todayTracked = "MeasureTodayTracked",
        todayElapsed = "MeasureTodayElapsed", weekTracked = "MeasureWeekTracked",
        weekElapsed = "MeasureWeekElapsed", priorityName = "MeasurePriorityName",
        priorityActual = "MeasurePriorityActual", priorityBudget = "MeasurePriorityBudget",
        leakName = "MeasureLeakName", leakActual = "MeasureLeakActual", leakLimit = "MeasureLeakLimit"
    }
    for key, name in pairs(names) do measures[key] = SKIN:GetMeasure(name) end
end

local function measureNumber(key, fallback)
    local measure = measures[key]
    if not measure then return fallback or 0 end
    local value = tonumber(measure:GetValue())
    if value == nil then return fallback or 0 end
    return value
end

local function measureText(key, fallback)
    local measure = measures[key]
    if not measure then return fallback or "" end
    local value = measure:GetStringValue()
    if value == nil or value == "" then return fallback or "" end
    return value
end

function Initialize()
    demoMode = numberVariable("DemoMode", 1) == 1
    cacheMeasures()
    if demoMode then
        loadDemo()
        statusText = "DEMO"
    else
        connected = false
        statusText = "CONECTANDO"
    end
    renderAll()
end

function Update()
    renderTimer()
    local minute = math.floor(os.time() / 60)
    if minute ~= lastFooterMinute then
        lastFooterMinute = minute
        renderFooter()
    end
    SKIN:Bang("!Redraw")
    return 0
end

function ApplyApi()
    if demoMode then return end
    local ok = measureNumber("ok", 0)
    if ok ~= 1 then
        connected = false
        contractError = false
        statusText = "SIN RED"
        renderAll()
        return
    end

    data.serverTime = measureNumber("serverTime", os.time())
    data.currentActive = measureNumber("currentActive", 0)
    data.currentName = measureText("currentName", "")
    data.currentStartedAt = measureText("currentStartedAt", "")
    data.currentElapsed = measureNumber("currentElapsed", 0)
    data.todayTracked = measureNumber("todayTracked", 0)
    data.todayElapsed = measureNumber("todayElapsed", 1)
    data.weekTracked = measureNumber("weekTracked", 0)
    data.weekElapsed = measureNumber("weekElapsed", 1)
    data.priorityName = measureText("priorityName", "")
    data.priorityActual = measureNumber("priorityActual", 0)
    data.priorityBudget = measureNumber("priorityBudget", 0)
    data.leakName = measureText("leakName", "")
    data.leakActual = measureNumber("leakActual", 0)
    data.leakLimit = measureNumber("leakLimit", 0)

    connected = true
    contractError = false
    statusText = "ONLINE"
    lastSyncUnix = os.time()
    renderAll()
end

function SetConnectionError()
    if demoMode then return end
    connected = false
    contractError = false
    statusText = "SIN RED"
    renderAll()
end

function SetContractError()
    if demoMode then return end
    connected = false
    contractError = true
    statusText = "SIN RED"
    renderAll()
end

function ManualRefresh()
    if demoMode then
        data.serverTime = os.time()
        lastSyncUnix = os.time()
        renderAll()
        return
    end

    statusText = "ACTUALIZANDO"
    contractError = false
    renderAll()
    -- "Update" fuerza una descarga nueva. "Reset" solo vacia la medida y
    -- !UpdateMeasure respeta el contador de UpdateRate, asi que el boton no
    -- volvia a consultar y la insignia se quedaba en ACTUALIZANDO hasta el
    -- siguiente sondeo automatico.
    SKIN:Bang("!CommandMeasure", "MeasureApi", "Update")
end
