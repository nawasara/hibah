<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => $this->workspaceLabel(), 'url' => '#'],
                ['label' => $this->title()],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <livewire:nawasara-hibah.proposal.section.table
            :purpose="$purpose"
            :form="$form"
            :bkType="$bkType"
            :purposeSegment="$purposeSegment"
            :segment="$segment"
            :key="'proposal-table-'.$purposeSegment.'-'.$segment" />
    </x-nawasara-ui::page.container>
</div>
