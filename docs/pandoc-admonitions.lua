--[[
  pandoc-admonitions.lua
  Converts MkDocs-style admonitions (!!!  and ???) into styled HTML <div>
  blocks for the PDF build (Pandoc + Paged.js).

  Supported syntax:
    !!! type "Optional title"        ← regular
    ??? type "Optional title"        ← collapsible (rendered expanded in PDF)
    !!! type                         ← no explicit title (type name used as title)

  Multi-paragraph admonitions: after the opening line Pandoc parses any
  blank-line-separated indented content as a CodeBlock.  This filter picks up
  that CodeBlock, re-parses it as Markdown and folds it into the admonition Div.

  Output structure:
    <div class="admonition <type>">
      <p><span class="admonition-title">Title</span></p>
      … content blocks …
    </div>

  Styling comes from print.css (PDF-only).
--]]

-- ── helpers ──────────────────────────────────────────────────────────────────

local function is_admonition_para(block)
    if block.t ~= 'Para' then return false end
    local c = block.c
    return #c >= 1 and c[1].t == 'Str' and (c[1].c == '!!!' or c[1].c == '???')
end

-- Returns (prefix, adtype, title_inlines, content_inlines)
local function parse_admonition_para(para)
    local inlines = para.c
    local prefix = inlines[1].c   -- '!!!' or '???'
    local idx    = 2

    -- skip leading spaces
    while idx <= #inlines and inlines[idx].t == 'Space' do idx = idx + 1 end

    -- admonition type (e.g. "tip", "warning", "danger")
    local adtype = 'note'
    if idx <= #inlines and inlines[idx].t == 'Str' then
        adtype = inlines[idx].c
        idx    = idx + 1
    end

    -- skip spaces
    while idx <= #inlines and inlines[idx].t == 'Space' do idx = idx + 1 end

    -- optional quoted title
    local title_inlines = pandoc.List()
    if idx <= #inlines and inlines[idx].t == 'Quoted' then
        title_inlines = pandoc.List(inlines[idx].c[2])
        idx = idx + 1
    end

    -- skip spaces / softbreaks before the first-paragraph content
    while idx <= #inlines
          and (inlines[idx].t == 'Space' or inlines[idx].t == 'SoftBreak') do
        idx = idx + 1
    end

    -- everything that remains is the first paragraph of the admonition
    local content_inlines = pandoc.List()
    for i = idx, #inlines do
        content_inlines:insert(inlines[i])
    end

    return prefix, adtype, title_inlines, content_inlines
end

-- Capitalise first letter
local function capitalise(s)
    return s:sub(1, 1):upper() .. s:sub(2)
end

-- ── main block-list processor ─────────────────────────────────────────────────

local function process_blocks(blocks)
    local result = pandoc.List()
    local i = 1
    while i <= #blocks do
        local block = blocks[i]

        if is_admonition_para(block) then
            local prefix, adtype, title_inlines, content_inlines =
                parse_admonition_para(block)

            -- Collect content blocks
            local content_blocks = pandoc.List()
            if #content_inlines > 0 then
                content_blocks:insert(pandoc.Para(content_inlines))
            end

            -- Greedy: absorb any following CodeBlocks (continuation paragraphs
            -- that Pandoc turned into code blocks due to 4-space indent).
            while i + 1 <= #blocks and blocks[i + 1].t == 'CodeBlock' do
                i = i + 1
                local code_text = blocks[i].c[2]
                local ok, parsed = pcall(pandoc.read, code_text, 'markdown')
                if ok then
                    for _, b in ipairs(parsed.blocks) do
                        content_blocks:insert(b)
                    end
                else
                    -- fallback: keep as plain paragraph
                    content_blocks:insert(pandoc.Para({ pandoc.Str(code_text) }))
                end
            end

            -- Build title span
            local title_content = #title_inlines > 0
                and title_inlines
                or pandoc.List({ pandoc.Str(capitalise(adtype)) })
            local title_span = pandoc.Span(title_content,
                                           pandoc.Attr('', { 'admonition-title' }))

            -- Assemble the Div
            local all_blocks = pandoc.List({ pandoc.Para({ title_span }) })
            for _, b in ipairs(content_blocks) do
                all_blocks:insert(b)
            end

            result:insert(pandoc.Div(all_blocks,
                                     pandoc.Attr('', { 'admonition', adtype })))
        else
            result:insert(block)
        end

        i = i + 1
    end
    return result
end

-- ── entry point ──────────────────────────────────────────────────────────────

function Pandoc(doc)
    doc.blocks = process_blocks(doc.blocks)
    return doc
end
