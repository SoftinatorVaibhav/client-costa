<?php
if ( ! defined('ABSPATH') ) {
	exit;
} ?>

<div class="x-row h-center">
	<div class="x-col-6 ts-content-head">
		<h1>General</h1>
		<p>General settings related to this membership plan test</p>
	</div>
</div>

<div class="x-row h-center">
	<div class="x-col-6">
		<ul class="inner-tabs">
			<li :class="{'current-item': subtab === 'general'}">
				<a href="#" @click.prevent="setTab('general', 'general')">General</a>
			</li>
			<li :class="{'current-item': subtab === 'supported_roles'}" v-if="plan.key !== 'default'">
				<a href="#" @click.prevent="setTab('general', 'supported_roles')">Supported roles</a>
			</li>
		</ul>
	</div>
</div>
<template v-if="subtab === 'general'">
	<div class="x-row h-center">
		<div class="x-col-6">
			<div class="ts-group">
				<div class="ts-group-head">
					<h3>General</h3>
				</div>
				<div class="x-row">
					<?php \Voxel\Form_Models\Text_Model::render( [
						'v-model' => 'plan.label',
						'label' => 'Label',
						'classes' => 'x-col-6',
					] ) ?>

					<?php \Voxel\Form_Models\Key_Model::render( [
						'v-model' => 'plan.key',
						'label' => 'Key',
						'editable' => false,
						'classes' => 'x-col-6',
					] ) ?>

					<?php \Voxel\Form_Models\Textarea_Model::render( [
						'v-model' => 'plan.description',
						'label' => 'Description',
						'classes' => 'x-col-12',
					] ) ?>

					<!-- New Input Field for Number of Adults -->
					<?php \Voxel\Form_Models\Number_Model::render( [
						'v-model' => 'plan.adults',
						'label' => 'Number of Adults',
						'classes' => 'x-col-6',
						'min' => 0, // Ensures no negative numbers
					] ) ?>

					<!-- New Input Field for Number of Children -->
					<?php \Voxel\Form_Models\Number_Model::render( [
						'v-model' => 'plan.children',
						'label' => 'Number of Children',
						'classes' => 'x-col-6',
						'min' => 0, // Ensures no negative numbers
					] ) ?>
					<!-- New Input Field for Number of Students -->
					<?php \Voxel\Form_Models\Number_Model::render( [
						'v-model' => 'plan.students',
						'label' => 'Number of Students',
						'classes' => 'x-col-6',
						'min' => 0, // Ensures no negative numbers
					] ) ?>
					<!-- New Input Field for Number of Seniors -->
					<?php \Voxel\Form_Models\Number_Model::render( [
						'v-model' => 'plan.seniors',
						'label' => 'Number of Seniors',
						'classes' => 'x-col-6',
						'min' => 0, // Ensures no negative numbers
					] ) ?>
				</div>
			</div>
			<div v-show="plan.key !== 'default'" class="x-row">
				<div class="x-col-12 h-center">
					<a href="#" @click.prevent="showArchive = !showArchive" class="ts-button ts-transparent full-width ">
						<i class="las la-arrow-down icon-sm"></i>
						Advanced
					</a>
				</div>
				<template v-if="showArchive">
					<div class="ts-group x-col-12">
						<div class="x-row">
							<div v-if="plan.archived" class="ts-form-group x-col-12">
								<p>Make this membership plan available to new users again.</p><br>
								<div class="basic-ul">
									<a href="#" class="ts-button ts-outline full-width" @click.prevent="archivePlan">
										<i class="las la-box icon-sm"></i>
										Unarchive plan
									</a>
								</div>
							</div>
							<div v-else class="ts-form-group x-col-12">
								<p>Archiving a membership plan will make it unavailable to new users. Users already on this plan will not be affected. Archived plans can be unarchived again.</p><br>
								<div class="basic-ul">
									<a href="#" class="ts-button ts-outline full-width" @click.prevent="archivePlan">
										<i class="las la-box icon-sm"></i>
										Archive this plan
									</a>
								</div>
							</div>
							<div v-if="plan.archived" class="ts-form-group x-col-12">
								<br><p>Delete this plan permanently. Users already on this plan will be assigned the default plan. This action cannot be undone.</p><br>
								<div class="basic-ul">
									<a href="#" class="ts-button ts-outline full-width" @click.prevent="deletePlan">
										<i class="las la-trash icon-sm"></i>
										Delete plan permanently
									</a>
								</div>
							</div>
						</div>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>
<template v-else-if="subtab === 'supported_roles'">
	<div class="x-row h-center">
		<div class="x-col-6">
			<div class="ts-group">
				<div class="ts-group-head">
					<h3>Supported roles</h3>
				</div>
				<div class="x-row">
					<?php \Voxel\Form_Models\Select_Model::render( [
						'v-model' => 'plan.settings.supported_roles',
						'label' => 'User roles that support purchasing this plan',
						'classes' => 'x-col-12',
						'choices' => [
							'all' => 'All: Supports every user role',
							'custom' => 'Custom: Manually set supported roles'
						],
					] ) ?>

					<?php \Voxel\Form_Models\Checkboxes_Model::render( [
						'v-if' => 'plan.settings.supported_roles === \'custom\'',
						'v-model' => 'plan.settings.supported_roles_custom',
						'label' => 'Select supported roles',
						'classes' => 'x-col-12',
						'choices' => array_map( function( $role ) {
							return $role->get_label();
						}, \Voxel\Role::get_voxel_roles() ),
					] ) ?>
				</div>
			</div>
		</div>
	</div>
</template>
